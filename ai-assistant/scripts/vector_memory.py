#!/usr/bin/env python3
"""
BosskuAI vector memory — sqlite-backed local-first retrieval.

Embedding providers (configure in vector-config.json):
  tfidf              Real TF-IDF term weighting, no downloads required. DEFAULT.
  local-hash         Original SHA-256 hash projection (compatibility alias).
  sentence-transformers  Neural embeddings if the library is installed.
  openai             OpenAI text-embedding-3-small if openai library installed.
"""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import math
import re
import sqlite3
import sys
from dataclasses import dataclass
from pathlib import Path

TOKEN_RE = re.compile(r"[A-Za-z0-9_./:-]{2,}")
HEADING_RE = re.compile(r"^(#{1,6})\s+(.*)$")
DATE_RE = re.compile(r"\b(20\d{2}-\d{2}-\d{2})\b")
INDEX_SCHEMA_VERSION = "2026-04-v1.8.0"


@dataclass
class Section:
    heading: str
    text: str
    start_line: int
    end_line: int


@dataclass
class Chunk:
    heading: str
    content: str
    token_count: int
    metadata: dict


# ---------------------------------------------------------------------------
# Embedders
# ---------------------------------------------------------------------------

class Embedder:
    provider_name = "unknown"
    def embed(self, text: str) -> list[float]:
        raise NotImplementedError
    def corpus_info(self) -> str:
        return "{}"
    def load_corpus_info(self, data: str) -> None:
        pass


class LocalHashEmbedder(Embedder):
    """
    Original SHA-256 hash projection — retained for compatibility only.
    Tokens map to fixed positions regardless of frequency; synonyms never match.
    Use tfidf or sentence-transformers for better retrieval.
    """
    provider_name = "local-hash"

    def __init__(self, dimensions: int, seed: str) -> None:
        self.dimensions = dimensions
        self.seed = seed

    def embed(self, text: str) -> list[float]:
        vector = [0.0] * self.dimensions
        for term in tokenize(text):
            digest = hashlib.sha256(f"{self.seed}:{term}".encode()).digest()
            idx = int.from_bytes(digest[:4], "big") % self.dimensions
            sign = 1.0 if digest[4] % 2 == 0 else -1.0
            weight = 1.0 + min(len(term), 24) / 24.0
            vector[idx] += sign * weight
        norm = math.sqrt(sum(v * v for v in vector))
        if norm == 0:
            return vector
        return [round(v / norm, 8) for v in vector]


class TfIdfEmbedder(Embedder):
    """
    TF-IDF hash projection — proper statistical term weighting, zero downloads.

    Vs local-hash:
      - 'the', 'and', 'use' get LOW IDF weight (common → low influence).
      - 'cofounder', 'ratchet', 'continuation' get HIGH weight (rare → high influence).
      - Similarity scores now reflect real term importance, not hash-collision noise.

    Requires corpus fit at sync time; IDF weights stored in meta table between syncs.
    """
    provider_name = "tfidf"

    def __init__(self, dimensions: int, seed: str) -> None:
        self.dimensions = dimensions
        self.seed = seed
        self.idf: dict[str, float] = {}

    def fit(self, texts: list[str]) -> None:
        N = max(len(texts), 1)
        df: dict[str, int] = {}
        for text in texts:
            for term in set(tokenize(text)):
                df[term] = df.get(term, 0) + 1
        self.idf = {term: math.log((N + 1) / (count + 1)) + 1.0 for term, count in df.items()}

    def corpus_info(self) -> str:
        return json.dumps(self.idf)

    def load_corpus_info(self, data: str) -> None:
        self.idf = json.loads(data) if data else {}

    def embed(self, text: str) -> list[float]:
        tokens = tokenize(text)
        if not tokens:
            return [0.0] * self.dimensions
        tf: dict[str, float] = {}
        for t in tokens:
            tf[t] = tf.get(t, 0.0) + 1.0
        total = sum(tf.values())
        vector = [0.0] * self.dimensions
        for term, count in tf.items():
            tfidf = (count / total) * self.idf.get(term, 1.0)
            digest = hashlib.sha256(f"{self.seed}:{term}".encode()).digest()
            idx = int.from_bytes(digest[:4], "big") % self.dimensions
            sign = 1.0 if digest[4] % 2 == 0 else -1.0
            vector[idx] += sign * tfidf
        norm = math.sqrt(sum(v * v for v in vector))
        if norm == 0:
            return vector
        return [round(v / norm, 8) for v in vector]


class SentenceTransformersEmbedder(Embedder):
    """
    Neural embeddings via sentence-transformers.
    Activates when installed: pip install sentence-transformers
    Default model: all-MiniLM-L6-v2 (true semantic similarity, ~80MB).
    """
    provider_name = "sentence-transformers"

    def __init__(self, model_name: str = "all-MiniLM-L6-v2") -> None:
        try:
            from sentence_transformers import SentenceTransformer  # type: ignore
        except ImportError as exc:
            raise ImportError(
                "sentence-transformers not installed.\n"
                "Run: pip install sentence-transformers\n"
                "Or switch provider to 'tfidf' in vector-config.json."
            ) from exc
        self.model = SentenceTransformer(model_name)
        self.model_name = model_name

    def embed(self, text: str) -> list[float]:
        vec = self.model.encode(text, convert_to_numpy=True)
        return [round(float(v), 8) for v in vec]


class OpenAIEmbedder(Embedder):
    """
    OpenAI text-embedding-3-small.
    Requires: pip install openai  and  OPENAI_API_KEY set.
    """
    provider_name = "openai"

    def __init__(self, model: str = "text-embedding-3-small") -> None:
        try:
            from openai import OpenAI  # type: ignore
        except ImportError as exc:
            raise ImportError("openai library not installed. Run: pip install openai") from exc
        self.client = OpenAI()
        self.model = model

    def embed(self, text: str) -> list[float]:
        resp = self.client.embeddings.create(input=text, model=self.model)
        return [round(v, 8) for v in resp.data[0].embedding]


# ---------------------------------------------------------------------------
# Path / config helpers
# ---------------------------------------------------------------------------

def repo_root() -> Path:
    return Path(__file__).resolve().parents[2]


def load_config(root: Path, config_path: str | None) -> tuple[dict, Path]:
    path = Path(config_path).expanduser().resolve() if config_path else root / "ai-assistant/memory/vector-config.json"
    if not path.exists():
        raise FileNotFoundError(f"Missing vector memory config: {path}")
    return json.loads(path.read_text(encoding="utf-8")), path


def resolve_path(root: Path, raw_path: str) -> Path:
    p = Path(raw_path)
    return p if p.is_absolute() else (root / p)


def display_path(path: Path, root: Path) -> str:
    try:
        return str(path.relative_to(root))
    except ValueError:
        return str(path)


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def sha256_text(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def config_signature(config: dict) -> str:
    relevant = {
        "index_schema_version": INDEX_SCHEMA_VERSION,
        "embedding": config.get("embedding", {}),
        "chunking": config.get("chunking", {}),
        "include": config.get("include", []),
    }
    return sha256_text(json.dumps(relevant, sort_keys=True))


# ---------------------------------------------------------------------------
# SQLite schema
# ---------------------------------------------------------------------------

def ensure_column(conn: sqlite3.Connection, table: str, column: str, definition: str) -> None:
    columns = set()
    for row in conn.execute(f"PRAGMA table_info({table})").fetchall():
        try:
            columns.add(row["name"])
        except (TypeError, KeyError, IndexError):
            columns.add(row[1])
    if column not in columns:
        conn.execute(f"ALTER TABLE {table} ADD COLUMN {column} {definition}")


def ensure_schema(conn: sqlite3.Connection) -> None:
    conn.executescript("""
        PRAGMA journal_mode = WAL;
        PRAGMA foreign_keys = ON;
        CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS documents (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          path TEXT NOT NULL UNIQUE, sha256 TEXT NOT NULL,
          chunk_count INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL,
          kind TEXT NOT NULL DEFAULT 'memory', metadata TEXT NOT NULL DEFAULT '{}'
        );
        CREATE TABLE IF NOT EXISTS chunks (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
          ordinal INTEGER NOT NULL, heading TEXT, content TEXT NOT NULL,
          token_count INTEGER NOT NULL, embedding TEXT NOT NULL,
          updated_at TEXT NOT NULL, metadata TEXT NOT NULL DEFAULT '{}',
          UNIQUE(document_id, ordinal)
        );
        CREATE INDEX IF NOT EXISTS idx_chunks_document_id ON chunks(document_id);
    """)
    ensure_column(conn, "documents", "kind", "TEXT NOT NULL DEFAULT 'memory'")
    ensure_column(conn, "documents", "metadata", "TEXT NOT NULL DEFAULT '{}'")
    ensure_column(conn, "chunks", "metadata", "TEXT NOT NULL DEFAULT '{}'")


def connect(db_path: Path) -> sqlite3.Connection:
    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    ensure_schema(conn)
    return conn


# ---------------------------------------------------------------------------
# Tokenisation
# ---------------------------------------------------------------------------

def tokenize(text: str) -> list[str]:
    words = TOKEN_RE.findall(text.lower())
    bigrams = [f"{l}::{r}" for l, r in zip(words, words[1:])]
    return words + bigrams


# ---------------------------------------------------------------------------
# Embedder factory
# ---------------------------------------------------------------------------

def build_embedder(config: dict) -> Embedder:
    cfg = config.get("embedding", {})
    provider = cfg.get("provider", "tfidf")
    dimensions = int(cfg.get("dimensions", 384))
    seed = str(cfg.get("seed", "bosskuai-vector-v2"))
    if provider in ("tfidf",):
        return TfIdfEmbedder(dimensions=dimensions, seed=seed)
    if provider in ("local-hash",):
        return LocalHashEmbedder(dimensions=dimensions, seed=seed)
    if provider == "sentence-transformers":
        return SentenceTransformersEmbedder(model_name=cfg.get("model", "all-MiniLM-L6-v2"))
    if provider == "openai":
        return OpenAIEmbedder(model=cfg.get("model", "text-embedding-3-small"))
    raise ValueError(
        f"Unknown embedding provider '{provider}'. "
        "Supported: tfidf (default), local-hash, sentence-transformers, openai."
    )


# ---------------------------------------------------------------------------
# Text processing
# ---------------------------------------------------------------------------

def split_sections(text: str) -> list[Section]:
    lines = text.splitlines()
    if not lines:
        return []
    sections: list[Section] = []
    current_heading = "Document"
    current_lines: list[str] = []
    start_line = 1
    for lineno, line in enumerate(lines, start=1):
        m = HEADING_RE.match(line.strip())
        if m and current_lines:
            body = "\n".join(current_lines).strip()
            if body:
                sections.append(Section(current_heading, body, start_line, lineno - 1))
            current_heading = m.group(2).strip()
            current_lines = [line]
            start_line = lineno
        elif m:
            current_heading = m.group(2).strip()
            current_lines = [line]
            start_line = lineno
        else:
            if not current_lines:
                start_line = lineno
            current_lines.append(line)
    trailing = "\n".join(current_lines).strip()
    if trailing:
        sections.append(Section(current_heading, trailing, start_line, len(lines)))
    return sections


def split_blocks(text: str) -> list[str]:
    text = re.sub(r"\n{3,}", "\n\n", text.strip())
    return [b.strip() for b in re.split(r"\n\s*\n", text) if b.strip()] if text else []


def split_long_block(block: str, max_chars: int) -> list[str]:
    pieces: list[str] = []
    current = ""
    for line in block.splitlines():
        line = line.rstrip()
        if not current:
            current = line
            continue
        candidate = f"{current}\n{line}".strip()
        if len(candidate) <= max_chars:
            current = candidate
        else:
            pieces.append(current.strip())
            current = line
    if current.strip():
        pieces.append(current.strip())
    if pieces:
        return pieces
    clean = block.strip()
    return [clean[i:i + max_chars].strip() for i in range(0, len(clean), max_chars)]


def chunk_blocks(blocks: list[str], target_chars: int, max_chars: int, overlap_blocks: int) -> list[str]:
    if not blocks:
        return []
    expanded: list[str] = []
    for b in blocks:
        expanded.extend(split_long_block(b, max_chars) if len(b) > max_chars else [b])
    chunks: list[str] = []
    cursor = 0
    while cursor < len(expanded):
        selected: list[str] = []
        total = 0
        start_cursor = cursor
        while cursor < len(expanded):
            blk = expanded[cursor]
            cand = total + len(blk) + (2 if selected else 0)
            if selected and total >= target_chars and cand > max_chars:
                break
            if selected and cand > max_chars:
                break
            selected.append(blk)
            total = cand
            cursor += 1
            if total >= target_chars:
                break
        if not selected:
            selected.append(expanded[cursor])
            cursor += 1
        chunks.append("\n\n".join(selected).strip())
        if cursor >= len(expanded):
            break
        cursor = max(start_cursor + 1, cursor - min(overlap_blocks, len(selected)))
    return [c for c in chunks if c]


def detect_structure(text: str) -> str:
    s = text.lstrip()
    if s.startswith("```"):
        return "code"
    if s.startswith("- ") or s.startswith("* ") or re.match(r"^\d+\.\s", s):
        return "list"
    return "text"


def classify_document(path: Path) -> str:
    name = path.name
    if "profile" in name:
        return "profile"
    if "project-understanding" in name:
        return "project"
    if "plan-log" in name:
        return "plan"
    if "learning-log" in name:
        return "learning"
    if "bug-patterns" in name:
        return "bugs"
    if "market-notes" in name:
        return "market"
    if "SKILL" in name:
        return "skill"
    return "memory"


def parse_date(text: str) -> str | None:
    m = DATE_RE.search(text)
    return m.group(1) if m else None


def normalize_text(text: str) -> str:
    return " ".join(TOKEN_RE.findall(text.lower()))


def humanize_stem(path: str) -> str:
    return re.sub(r"[-_]+", " ", Path(path).stem).strip()


GENERIC_HEADING_DEFAULTS = [
    "document", "entries", "entry template", "what to store", "what belongs here",
    "what not to store", "suggested format", "promotion guidance", "active entries", "bosskuai",
    "fast path", "when to open the playbook", "default output", "verification",
]


def generic_headings(config: dict) -> set[str]:
    return {normalize_text(i) for i in config.get("generic_headings", GENERIC_HEADING_DEFAULTS) if normalize_text(i)}


def document_title_for_chunks(chunks: list[Chunk], fallback_path: Path, config: dict) -> str:
    generic = generic_headings(config)
    for chunk in chunks:
        norm = normalize_text(chunk.heading)
        if norm and norm not in generic:
            return chunk.heading
    return humanize_stem(str(fallback_path))


def build_chunks(text: str, source_path: Path, config: dict) -> list[Chunk]:
    cc = config.get("chunking", {})
    target_chars = int(cc.get("target_chars", 700))
    max_chars = int(cc.get("max_chars", 1100))
    min_chars = int(cc.get("min_chars", 180))
    overlap_blocks = int(cc.get("overlap_blocks", 1))
    document_kind = classify_document(source_path)
    built: list[Chunk] = []
    for si, section in enumerate(split_sections(text)):
        for ci, content in enumerate(chunk_blocks(split_blocks(section.text), target_chars, max_chars, overlap_blocks)):
            if len(content) < min_chars and built:
                last = built[-1]
                if last.heading == section.heading and len(last.content) + len(content) + 2 <= max_chars:
                    last.content = f"{last.content}\n\n{content}".strip()
                    last.token_count = len(tokenize(last.content))
                    continue
            built.append(Chunk(
                heading=section.heading, content=content,
                token_count=len(tokenize(content)),
                metadata={
                    "document_kind": document_kind, "source_file": source_path.name,
                    "section_index": si, "chunk_index": ci,
                    "start_line": section.start_line, "end_line": section.end_line,
                    "structure": detect_structure(content),
                    "date_hint": parse_date(section.heading) or parse_date(content),
                },
            ))
    return built


# ---------------------------------------------------------------------------
# Scoring
# ---------------------------------------------------------------------------

def source_weight(path: str, config: dict) -> float:
    weights = config.get("source_weights", {})
    return float(weights.get(Path(path).name, weights.get(path, 0.0)))


def document_aliases(path: str, document_title: str, config: dict) -> list[str]:
    basename = Path(path).name
    aliases = [humanize_stem(path), document_title]
    aliases.extend(config.get("document_aliases", {}).get(basename, []))
    seen: set[str] = set()
    ordered: list[str] = []
    for alias in aliases:
        norm = normalize_text(alias)
        if norm and norm not in seen:
            seen.add(norm)
            ordered.append(alias)
    return ordered


def phrase_in_query(query_text: str, phrase: str) -> bool:
    nq = normalize_text(query_text)
    np_ = normalize_text(phrase)
    if not np_:
        return False
    if np_ in nq:
        return True
    return set(TOKEN_RE.findall(np_)).issubset(set(TOKEN_RE.findall(nq)))


def document_name_score(query_text: str, row: sqlite3.Row, document_metadata: dict, config: dict) -> float:
    aliases = document_aliases(row["path"], str(document_metadata.get("document_title", "")), config)
    query_terms = tokenize(query_text)
    nq = normalize_text(query_text)
    best = 0.0
    for alias in aliases:
        na = normalize_text(alias)
        if na:
            ov = overlap_score(query_terms, tokenize(na))
            boost = 1.0 if na in nq else 0.0
            best = max(best, ov + boost)
    return round(min(best, 1.5), 6)


def intent_hint_score(query_text: str, row: sqlite3.Row, document_metadata: dict, config: dict) -> float:
    basename = Path(row["path"]).name
    document_kind = row["document_kind"]
    best = 0.0
    for hint in config.get("intent_hints", []):
        if not any(phrase_in_query(query_text, p) for p in hint.get("phrases", [])):
            continue
        if basename not in set(hint.get("boost_files", [])) and document_kind not in set(hint.get("boost_kinds", [])):
            continue
        title_phrases = hint.get("title_phrases", [])
        if title_phrases and not any(
            phrase_in_query(str(document_metadata.get("document_title", "")), p) for p in title_phrases
        ):
            continue
        best = max(best, float(hint.get("boost", 1.0)))
    return round(best, 6)


def noise_penalty(heading: str, content: str, lexical: float, document_name: float, intent: float, config: dict) -> float:
    rc = config.get("retrieval", {})
    penalty = 0.0
    if normalize_text(heading) in generic_headings(config) and document_name == 0 and intent == 0:
        penalty += float(rc.get("generic_heading_penalty", 0.04))
    markers = [m.lower() for m in config.get("noise_markers", [])]
    if any(m in content.lower() for m in markers) and lexical < 0.3 and intent == 0:
        penalty += float(rc.get("noise_penalty", 0.06))
    return round(penalty, 6)


def retrieval_weights(config: dict, strategy: str) -> dict[str, float]:
    rc = config.get("retrieval", {})
    if strategy == "semantic-only":
        return {"semantic": 1.0, "lexical": 0.0, "heading": 0.0, "document_name": 0.0, "intent": 0.0, "recency": 0.0, "source": 0.0}
    return {
        "semantic": float(rc.get("semantic_weight", 0.45)),
        "lexical": float(rc.get("lexical_weight", 0.2)),
        "heading": float(rc.get("heading_weight", 0.08)),
        "document_name": float(rc.get("document_name_weight", 0.12)),
        "intent": float(rc.get("intent_weight", 0.08)),
        "recency": float(rc.get("recency_weight", 0.03)),
        "source": float(rc.get("source_weight", 0.04)),
    }


def cosine_similarity(a: list[float], b: list[float]) -> float:
    return sum(x * y for x, y in zip(a, b))


def overlap_score(query_terms: list[str], target_terms: list[str]) -> float:
    qs, ts = set(query_terms), set(target_terms)
    if not qs or not ts:
        return 0.0
    return len(qs & ts) / len(qs)


def recency_score(date_hint: str | None, now: dt.datetime) -> float:
    if not date_hint:
        return 0.0
    try:
        parsed = dt.date.fromisoformat(date_hint)
    except ValueError:
        return 0.0
    return round(1.0 / (1.0 + (max((now.date() - parsed).days, 0) / 120.0)), 6)


def preview(text: str, limit: int = 220) -> str:
    c = " ".join(text.split())
    return c if len(c) <= limit else c[:limit - 3] + "..."


def should_skip_hit(semantic: float, lexical: float, combined: float, content: str,
                    document_name: float, intent: float, config: dict) -> bool:
    rc = config.get("retrieval", {})
    if combined < float(rc.get("min_combined_score", 0.12)):
        return True
    if lexical == 0 and semantic < float(rc.get("min_semantic_without_lexical", 0.18)) and document_name == 0 and intent == 0:
        return True
    markers = [m.lower() for m in config.get("noise_markers", [])]
    return bool(any(m in content.lower() for m in markers) and lexical == 0 and intent == 0)


def score_hit(query_text: str, query_vector: list[float], row: sqlite3.Row, config: dict, strategy: str) -> dict | None:
    rc = config.get("retrieval", {})
    weights = retrieval_weights(config, strategy)
    query_terms = tokenize(query_text)
    content = row["content"]
    chunk_metadata = json.loads(row["chunk_metadata"] or "{}")
    document_metadata = json.loads(row["document_metadata"] or "{}")
    semantic = max(cosine_similarity(query_vector, json.loads(row["embedding"])), 0.0)
    lexical = overlap_score(query_terms, tokenize(content))
    heading = overlap_score(query_terms, tokenize(row["heading"] or ""))
    document_name = document_name_score(query_text, row, document_metadata, config)
    intent = intent_hint_score(query_text, row, document_metadata, config)
    recency = recency_score(
        chunk_metadata.get("date_hint") or str(document_metadata.get("mtime", ""))[:10],
        dt.datetime.now(dt.timezone.utc),
    )
    source = source_weight(row["path"], config)
    penalty = noise_penalty(row["heading"] or "", content, lexical, document_name, intent, config)
    combined = round(
        semantic * weights["semantic"] + lexical * weights["lexical"] + heading * weights["heading"]
        + document_name * weights["document_name"] + intent * weights["intent"]
        + recency * weights["recency"] + source * weights["source"] - penalty, 6,
    )
    if should_skip_hit(semantic, lexical, combined, content, document_name, intent, config):
        return None
    return {
        "score": combined, "path": row["path"], "ordinal": row["ordinal"],
        "heading": row["heading"] or "Document",
        "preview": preview(content, limit=int(rc.get("preview_chars", 220))),
        "components": {"semantic": round(semantic, 6), "lexical": round(lexical, 6),
                       "heading": round(heading, 6), "document_name": round(document_name, 6),
                       "intent": round(intent, 6), "recency": round(recency, 6),
                       "source": round(source, 6), "penalty": round(penalty, 6)},
        "metadata": {
            "document_kind": row["document_kind"], "source_file": Path(row["path"]).name,
            "structure": chunk_metadata.get("structure", "text"),
            "date_hint": chunk_metadata.get("date_hint"),
            "document_title": document_metadata.get("document_title"),
        },
    }


# ---------------------------------------------------------------------------
# Public retrieval API — shared by query_command and eval
# ---------------------------------------------------------------------------

def retrieve_from_conn(conn: sqlite3.Connection, embedder: Embedder, query_text: str,
                       config: dict, limit: int = 5, strategy: str = "hybrid") -> list[dict]:
    """Score all chunks in an open DB connection. Shared by query and eval paths."""
    query_vector = embedder.embed(query_text)
    rows = conn.execute("""
        SELECT documents.path, documents.kind AS document_kind,
               documents.metadata AS document_metadata,
               chunks.ordinal, chunks.heading, chunks.content,
               chunks.embedding, chunks.metadata AS chunk_metadata
        FROM chunks JOIN documents ON documents.id = chunks.document_id
    """).fetchall()

    scored = [hit for row in rows if (hit := score_hit(query_text, query_vector, row, config, strategy))]
    scored.sort(key=lambda h: (
        h["score"], h["components"]["intent"], h["components"]["document_name"],
        h["components"]["heading"], h["components"]["lexical"],
        h["components"]["source"], h["components"]["semantic"], -h["ordinal"],
    ), reverse=True)

    rc = config.get("retrieval", {})
    max_per_doc = int(rc.get("max_per_document", 2))
    top: list[dict] = []
    per_doc: dict[str, int] = {}
    for hit in scored:
        if per_doc.get(hit["path"], 0) >= max_per_doc:
            continue
        top.append(hit)
        per_doc[hit["path"]] = per_doc.get(hit["path"], 0) + 1
        if len(top) >= limit:
            break
    return top


def retrieve_text_files(query_text: str, file_paths: list[Path], config: dict,
                        limit: int = 5, strategy: str = "hybrid") -> list[dict]:
    """
    Build an in-memory DB from arbitrary files and query using the production scorer.
    Used by eval_workspace.py so it tests the SAME path as production.
    """
    conn = sqlite3.connect(":memory:")
    conn.row_factory = sqlite3.Row
    ensure_schema(conn)

    embedder = build_embedder(config)
    texts = []
    valid: list[tuple[Path, str]] = []
    for p in file_paths:
        if p.exists():
            t = p.read_text(encoding="utf-8")
            texts.append(t)
            valid.append((p, t))

    if hasattr(embedder, "fit") and texts:
        embedder.fit(texts)

    timestamp = utc_now()
    for path, text in valid:
        chunks = build_chunks(text, path, config)
        doc_kind = classify_document(path)
        document_title = document_title_for_chunks(chunks, path, config)
        doc_meta = {"source_file": path.name, "document_kind": doc_kind,
                    "document_title": document_title, "mtime": timestamp}
        with conn:
            cur = conn.execute(
                "INSERT INTO documents(path,sha256,chunk_count,updated_at,kind,metadata) VALUES(?,?,?,?,?,?)",
                (str(path), sha256_text(text), len(chunks), timestamp, doc_kind, json.dumps(doc_meta)),
            )
            doc_id = cur.lastrowid
            for ordinal, chunk in enumerate(chunks):
                conn.execute(
                    "INSERT INTO chunks(document_id,ordinal,heading,content,token_count,embedding,updated_at,metadata) "
                    "VALUES(?,?,?,?,?,?,?,?)",
                    (doc_id, ordinal, chunk.heading, chunk.content, chunk.token_count,
                     json.dumps(embedder.embed(chunk.content)), timestamp, json.dumps(chunk.metadata)),
                )
    return retrieve_from_conn(conn, embedder, query_text, config, limit=limit, strategy=strategy)


# ---------------------------------------------------------------------------
# Sync
# ---------------------------------------------------------------------------

def sync_command(root: Path, config: dict, config_file: Path) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    include_paths = list(config.get("include", []))
    tracked_paths = {str(Path(p)) for p in include_paths}
    embedder = build_embedder(config)

    conn = connect(db_path)
    existing = {row["path"]: row for row in conn.execute("SELECT id, path, sha256 FROM documents")}
    stored_sig = conn.execute("SELECT value FROM meta WHERE key='index_signature'").fetchone()
    force_reindex = not stored_sig or stored_sig["value"] != config_signature(config)

    # Collect texts for corpus fit (TF-IDF)
    all_texts: list[str] = []
    valid_paths: list[tuple[str, Path]] = []
    for raw in include_paths:
        abs_path = resolve_path(root, raw)
        if abs_path.exists():
            text = abs_path.read_text(encoding="utf-8")
            all_texts.append(text)
            valid_paths.append((str(Path(raw)), abs_path))

    if hasattr(embedder, "fit") and all_texts:
        embedder.fit(all_texts)
        with conn:
            conn.execute("INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)", ("corpus_info", embedder.corpus_info()))

    updated = skipped = removed = 0
    for (relative_path, absolute_path), text in zip(valid_paths, all_texts):
        digest = sha256_text(text)
        doc_row = existing.get(relative_path)
        if not force_reindex and doc_row and doc_row["sha256"] == digest:
            skipped += 1
            continue
        chunks = build_chunks(text, absolute_path, config)
        timestamp = utc_now()
        document_title = document_title_for_chunks(chunks, absolute_path, config)
        doc_kind = classify_document(absolute_path)
        doc_meta = {
            "source_file": absolute_path.name, "document_kind": doc_kind,
            "document_title": document_title,
            "mtime": dt.datetime.fromtimestamp(absolute_path.stat().st_mtime, tz=dt.timezone.utc).replace(microsecond=0).isoformat(),
        }
        with conn:
            if doc_row:
                doc_id = int(doc_row["id"])
                conn.execute("UPDATE documents SET sha256=?,chunk_count=?,updated_at=?,kind=?,metadata=? WHERE id=?",
                             (digest, len(chunks), timestamp, doc_kind, json.dumps(doc_meta), doc_id))
                conn.execute("DELETE FROM chunks WHERE document_id=?", (doc_id,))
            else:
                cur = conn.execute(
                    "INSERT INTO documents(path,sha256,chunk_count,updated_at,kind,metadata) VALUES(?,?,?,?,?,?)",
                    (relative_path, digest, len(chunks), timestamp, doc_kind, json.dumps(doc_meta)),
                )
                doc_id = int(cur.lastrowid)
            for ordinal, chunk in enumerate(chunks):
                conn.execute(
                    "INSERT INTO chunks(document_id,ordinal,heading,content,token_count,embedding,updated_at,metadata) VALUES(?,?,?,?,?,?,?,?)",
                    (doc_id, ordinal, chunk.heading, chunk.content, chunk.token_count,
                     json.dumps(embedder.embed(chunk.content)), timestamp, json.dumps(chunk.metadata)),
                )
        updated += 1

    for row in conn.execute("SELECT id, path FROM documents").fetchall():
        if row["path"] not in tracked_paths:
            with conn:
                conn.execute("DELETE FROM documents WHERE id=?", (row["id"],))
            removed += 1

    with conn:
        conn.execute("INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)", ("config_path", display_path(config_file, root)))
        conn.execute("INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)", ("last_sync_at", utc_now()))
        conn.execute("INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)", ("embedding_provider", embedder.provider_name))
        conn.execute("INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)", ("index_signature", config_signature(config)))

    total_docs = conn.execute("SELECT COUNT(*) FROM documents").fetchone()[0]
    total_chunks = conn.execute("SELECT COUNT(*) FROM chunks").fetchone()[0]
    print(f"Vector memory synced: db={db_path}")
    print(f"Provider: {embedder.provider_name}")
    if isinstance(embedder, TfIdfEmbedder):
        print(f"IDF vocabulary: {len(embedder.idf)} terms")
    if force_reindex:
        print("Reindexed: config changed or index signature missing.")
    print(f"Updated: {updated}  Skipped: {skipped}  Removed: {removed}")
    print(f"Documents: {total_docs}  Chunks: {total_chunks}")
    return 0


# ---------------------------------------------------------------------------
# Query
# ---------------------------------------------------------------------------

def query_command(root: Path, config: dict, query_text: str, limit: int, json_output: bool, strategy: str) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    if not db_path.exists():
        print(f"Vector memory missing: {db_path}. Run sync first.", file=sys.stderr)
        return 1
    conn = connect(db_path)
    embedder = build_embedder(config)
    corpus_row = conn.execute("SELECT value FROM meta WHERE key='corpus_info'").fetchone()
    if corpus_row and hasattr(embedder, "load_corpus_info"):
        embedder.load_corpus_info(corpus_row["value"])
    top_hits = retrieve_from_conn(conn, embedder, query_text, config, limit=limit, strategy=strategy)
    if json_output:
        print(json.dumps(top_hits, indent=2))
        return 0
    if not top_hits:
        print("No vector hits found.")
        return 0
    rc = config.get("retrieval", {})
    for i, hit in enumerate(top_hits, start=1):
        c = hit["components"]
        print(f"#{i} score={hit['score']} path={hit['path']} heading={hit['heading']} chunk={hit['ordinal']} "
              f"(semantic={c['semantic']} lexical={c['lexical']} doc={c['document_name']} intent={c['intent']} penalty={c['penalty']})")
        print(f"    {hit['preview']}")
    return 0


# ---------------------------------------------------------------------------
# Status
# ---------------------------------------------------------------------------

def status_command(root: Path, config: dict, config_file: Path) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    if not db_path.exists():
        print(f"Vector memory not initialized. Config: {display_path(config_file, root)}")
        return 0
    conn = connect(db_path)
    total_docs = conn.execute("SELECT COUNT(*) FROM documents").fetchone()[0]
    total_chunks = conn.execute("SELECT COUNT(*) FROM chunks").fetchone()[0]
    last_sync = conn.execute("SELECT value FROM meta WHERE key='last_sync_at'").fetchone()
    provider = conn.execute("SELECT value FROM meta WHERE key='embedding_provider'").fetchone()
    corpus_row = conn.execute("SELECT value FROM meta WHERE key='corpus_info'").fetchone()
    idf_terms = len(json.loads(corpus_row["value"])) if corpus_row else 0
    rc = config.get("retrieval", {})
    print(f"DB: {db_path}")
    print(f"Provider: {provider['value'] if provider else build_embedder(config).provider_name}")
    if idf_terms:
        print(f"IDF vocabulary: {idf_terms} terms")
    print(f"Documents: {total_docs}  Chunks: {total_chunks}")
    print(f"Last sync: {last_sync['value'] if last_sync else 'unknown'}")
    print("Indexed files:")
    for p in config.get("include", []):
        print(f"  - {p}")
    return 0


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="BosskuAI sqlite-backed local-first memory retrieval.")
    parser.add_argument("--root", default=str(repo_root()))
    parser.add_argument("--config", default=None)
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("sync")
    q = sub.add_parser("query")
    q.add_argument("text")
    q.add_argument("--limit", type=int, default=5)
    q.add_argument("--strategy", choices=["hybrid", "semantic-only"], default="hybrid")
    q.add_argument("--json", action="store_true")
    sub.add_parser("status")
    return parser


def main() -> int:
    args = build_parser().parse_args()
    root = Path(args.root).expanduser().resolve()
    config, config_file = load_config(root, args.config)
    if args.command == "sync":
        return sync_command(root, config, config_file)
    if args.command == "query":
        return query_command(root, config, args.text, limit=args.limit, json_output=args.json, strategy=args.strategy)
    if args.command == "status":
        return status_command(root, config, config_file)
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
