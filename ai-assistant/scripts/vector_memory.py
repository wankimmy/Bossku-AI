#!/usr/bin/env python3

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
INDEX_SCHEMA_VERSION = "2026-04-hardening-1"


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


class Embedder:
    provider_name = "unknown"

    def embed(self, text: str) -> list[float]:
        raise NotImplementedError


class LocalHashEmbedder(Embedder):
    provider_name = "local-hash"

    def __init__(self, dimensions: int, seed: str) -> None:
        self.dimensions = dimensions
        self.seed = seed

    def embed(self, text: str) -> list[float]:
        vector = [0.0] * self.dimensions
        for term in tokenize(text):
            digest = hashlib.sha256(f"{self.seed}:{term}".encode("utf-8")).digest()
            index = int.from_bytes(digest[:4], "big") % self.dimensions
            sign = 1.0 if digest[4] % 2 == 0 else -1.0
            weight = 1.0 + min(len(term), 24) / 24.0
            vector[index] += sign * weight

        norm = math.sqrt(sum(value * value for value in vector))
        if norm == 0:
            return vector
        return [round(value / norm, 8) for value in vector]


def repo_root() -> Path:
    return Path(__file__).resolve().parents[2]


def load_config(root: Path, config_path: str | None) -> tuple[dict, Path]:
    path = Path(config_path).expanduser().resolve() if config_path else root / "ai-assistant/memory/vector-config.json"
    if not path.exists():
        raise FileNotFoundError(f"Missing vector memory config: {path}")
    return json.loads(path.read_text(encoding="utf-8")), path


def resolve_path(root: Path, raw_path: str) -> Path:
    path = Path(raw_path)
    return path if path.is_absolute() else (root / path)


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
    conn.executescript(
        """
        PRAGMA journal_mode = WAL;
        PRAGMA foreign_keys = ON;

        CREATE TABLE IF NOT EXISTS meta (
          key TEXT PRIMARY KEY,
          value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS documents (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          path TEXT NOT NULL UNIQUE,
          sha256 TEXT NOT NULL,
          chunk_count INTEGER NOT NULL DEFAULT 0,
          updated_at TEXT NOT NULL,
          kind TEXT NOT NULL DEFAULT 'memory',
          metadata TEXT NOT NULL DEFAULT '{}'
        );

        CREATE TABLE IF NOT EXISTS chunks (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
          ordinal INTEGER NOT NULL,
          heading TEXT,
          content TEXT NOT NULL,
          token_count INTEGER NOT NULL,
          embedding TEXT NOT NULL,
          updated_at TEXT NOT NULL,
          metadata TEXT NOT NULL DEFAULT '{}',
          UNIQUE(document_id, ordinal)
        );

        CREATE INDEX IF NOT EXISTS idx_chunks_document_id ON chunks(document_id);
        """
    )
    ensure_column(conn, "documents", "kind", "TEXT NOT NULL DEFAULT 'memory'")
    ensure_column(conn, "documents", "metadata", "TEXT NOT NULL DEFAULT '{}'")
    ensure_column(conn, "chunks", "metadata", "TEXT NOT NULL DEFAULT '{}'")


def connect(db_path: Path) -> sqlite3.Connection:
    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    ensure_schema(conn)
    return conn


def tokenize(text: str) -> list[str]:
    words = TOKEN_RE.findall(text.lower())
    bigrams = [f"{left}::{right}" for left, right in zip(words, words[1:])]
    return words + bigrams


def build_embedder(config: dict) -> Embedder:
    embedding_cfg = config.get("embedding", {})
    provider = embedding_cfg.get("provider", "local-hash")
    if provider == "local-hash":
        return LocalHashEmbedder(
            dimensions=int(embedding_cfg.get("dimensions", 256)),
            seed=str(embedding_cfg.get("seed", "bosskuai-vector-v1")),
        )
    raise ValueError(
        f"Unsupported embedding provider '{provider}'. "
        "BosskuAI ships with local-hash by default; add another provider in vector_memory.py if you want stronger embeddings."
    )


def split_sections(text: str) -> list[Section]:
    lines = text.splitlines()
    if not lines:
        return []

    sections: list[Section] = []
    current_heading = "Document"
    current_lines: list[str] = []
    start_line = 1

    for lineno, line in enumerate(lines, start=1):
        heading_match = HEADING_RE.match(line.strip())
        if heading_match and current_lines:
            body = "\n".join(current_lines).strip()
            if body:
                sections.append(Section(current_heading, body, start_line, lineno - 1))
            current_heading = heading_match.group(2).strip()
            current_lines = [line]
            start_line = lineno
        elif heading_match:
            current_heading = heading_match.group(2).strip()
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
    if not text:
        return []
    return [block.strip() for block in re.split(r"\n\s*\n", text) if block.strip()]


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
            continue
        pieces.append(current.strip())
        current = line

    if current.strip():
        pieces.append(current.strip())

    if pieces:
        return pieces

    clean = block.strip()
    return [clean[index : index + max_chars].strip() for index in range(0, len(clean), max_chars)]


def chunk_blocks(blocks: list[str], target_chars: int, max_chars: int, overlap_blocks: int) -> list[str]:
    if not blocks:
        return []

    expanded_blocks: list[str] = []
    for block in blocks:
        if len(block) > max_chars:
            expanded_blocks.extend(split_long_block(block, max_chars=max_chars))
        else:
            expanded_blocks.append(block)

    chunks: list[str] = []
    cursor = 0
    while cursor < len(expanded_blocks):
        selected: list[str] = []
        total = 0
        start_cursor = cursor
        while cursor < len(expanded_blocks):
            block = expanded_blocks[cursor]
            candidate_total = total + len(block) + (2 if selected else 0)
            if selected and total >= target_chars and candidate_total > max_chars:
                break
            if selected and candidate_total > max_chars:
                break
            selected.append(block)
            total = candidate_total
            cursor += 1
            if total >= target_chars:
                break

        if not selected:
            selected.append(expanded_blocks[cursor])
            cursor += 1

        chunks.append("\n\n".join(selected).strip())

        if cursor >= len(expanded_blocks):
            break

        cursor = max(start_cursor + 1, cursor - min(overlap_blocks, len(selected)))

    return [chunk for chunk in chunks if chunk]


def detect_structure(text: str) -> str:
    stripped = text.lstrip()
    if stripped.startswith("```"):
        return "code"
    if stripped.startswith("- ") or stripped.startswith("* ") or re.match(r"^\d+\.\s", stripped):
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
    return "memory"


def parse_date(text: str) -> str | None:
    match = DATE_RE.search(text)
    return match.group(1) if match else None


def normalize_text(text: str) -> str:
    return " ".join(TOKEN_RE.findall(text.lower()))


def humanize_stem(path: str) -> str:
    stem = Path(path).stem
    return re.sub(r"[-_]+", " ", stem).strip()


def generic_headings(config: dict) -> set[str]:
    defaults = [
        "document",
        "entries",
        "entry template",
        "what to store",
        "what belongs here",
        "what not to store",
        "suggested format",
        "promotion guidance",
        "active entries",
        "bosskuai",
    ]
    return {normalize_text(item) for item in config.get("generic_headings", defaults) if normalize_text(item)}


def document_title_for_chunks(chunks: list[Chunk], fallback_path: Path, config: dict) -> str:
    generic = generic_headings(config)
    for chunk in chunks:
        normalized_heading = normalize_text(chunk.heading)
        if normalized_heading and normalized_heading not in generic:
            return chunk.heading
    return humanize_stem(str(fallback_path))


def build_chunks(text: str, source_path: Path, config: dict) -> list[Chunk]:
    chunk_cfg = config.get("chunking", {})
    target_chars = int(chunk_cfg.get("target_chars", 700))
    max_chars = int(chunk_cfg.get("max_chars", 1100))
    min_chars = int(chunk_cfg.get("min_chars", 180))
    overlap_blocks = int(chunk_cfg.get("overlap_blocks", 1))
    document_kind = classify_document(source_path)

    built: list[Chunk] = []
    for section_index, section in enumerate(split_sections(text)):
        blocks = split_blocks(section.text)
        for chunk_index, content in enumerate(
            chunk_blocks(blocks, target_chars=target_chars, max_chars=max_chars, overlap_blocks=overlap_blocks)
        ):
            if len(content) < min_chars and built:
                last = built[-1]
                if last.heading == section.heading and len(last.content) + len(content) + 2 <= max_chars:
                    last.content = f"{last.content}\n\n{content}".strip()
                    last.token_count = len(tokenize(last.content))
                    continue

            metadata = {
                "document_kind": document_kind,
                "source_file": source_path.name,
                "section_index": section_index,
                "chunk_index": chunk_index,
                "start_line": section.start_line,
                "end_line": section.end_line,
                "structure": detect_structure(content),
                "date_hint": parse_date(section.heading) or parse_date(content),
            }
            built.append(
                Chunk(
                    heading=section.heading,
                    content=content,
                    token_count=len(tokenize(content)),
                    metadata=metadata,
                )
            )
    return built


def source_weight(path: str, config: dict) -> float:
    weights = config.get("source_weights", {})
    basename = Path(path).name
    return float(weights.get(basename, weights.get(path, 0.0)))


def document_aliases(path: str, document_title: str, config: dict) -> list[str]:
    basename = Path(path).name
    aliases = [humanize_stem(path), document_title]
    aliases.extend(config.get("document_aliases", {}).get(basename, []))
    seen = set()
    ordered = []
    for alias in aliases:
        normalized = normalize_text(alias)
        if normalized and normalized not in seen:
            seen.add(normalized)
            ordered.append(alias)
    return ordered


def phrase_in_query(query_text: str, phrase: str) -> bool:
    normalized_query = normalize_text(query_text)
    normalized_phrase = normalize_text(phrase)
    if not normalized_phrase:
        return False
    if normalized_phrase in normalized_query:
        return True
    phrase_tokens = set(TOKEN_RE.findall(normalized_phrase))
    query_tokens = set(TOKEN_RE.findall(normalized_query))
    return bool(phrase_tokens) and phrase_tokens.issubset(query_tokens)


def document_name_score(query_text: str, row: sqlite3.Row, document_metadata: dict, config: dict) -> float:
    aliases = document_aliases(row["path"], str(document_metadata.get("document_title", "")), config)
    query_terms = tokenize(query_text)
    normalized_query = normalize_text(query_text)
    best = 0.0
    for alias in aliases:
        normalized_alias = normalize_text(alias)
        if not normalized_alias:
            continue
        overlap = overlap_score(query_terms, tokenize(normalized_alias))
        boost = 1.0 if normalized_alias in normalized_query else 0.0
        best = max(best, overlap + boost)
    return round(min(best, 1.5), 6)


def intent_hint_score(query_text: str, row: sqlite3.Row, document_metadata: dict, config: dict) -> float:
    basename = Path(row["path"]).name
    document_kind = row["document_kind"]
    best = 0.0
    for hint in config.get("intent_hints", []):
        phrases = hint.get("phrases", [])
        if not any(phrase_in_query(query_text, phrase) for phrase in phrases):
            continue
        target_files = set(hint.get("boost_files", []))
        target_kinds = set(hint.get("boost_kinds", []))
        if basename not in target_files and document_kind not in target_kinds:
            continue
        title_phrases = hint.get("title_phrases", [])
        if title_phrases and not any(
            phrase_in_query(str(document_metadata.get("document_title", "")), phrase) for phrase in title_phrases
        ):
            continue
        best = max(best, float(hint.get("boost", 1.0)))
    return round(best, 6)


def noise_penalty(
    heading: str,
    content: str,
    lexical: float,
    document_name: float,
    intent: float,
    config: dict,
) -> float:
    retrieval_cfg = config.get("retrieval", {})
    penalty = 0.0
    lowered = content.lower()
    if normalize_text(heading) in generic_headings(config) and document_name == 0 and intent == 0:
        penalty += float(retrieval_cfg.get("generic_heading_penalty", 0.04))
    markers = [marker.lower() for marker in config.get("noise_markers", [])]
    if any(marker in lowered for marker in markers) and lexical < 0.3 and intent == 0:
        penalty += float(retrieval_cfg.get("noise_penalty", 0.06))
    return round(penalty, 6)


def retrieval_weights(config: dict, strategy: str) -> dict[str, float]:
    retrieval_cfg = config.get("retrieval", {})
    if strategy == "semantic-only":
        return {
            "semantic": 1.0,
            "lexical": 0.0,
            "heading": 0.0,
            "document_name": 0.0,
            "intent": 0.0,
            "recency": 0.0,
            "source": 0.0,
        }
    return {
        "semantic": float(retrieval_cfg.get("semantic_weight", 0.45)),
        "lexical": float(retrieval_cfg.get("lexical_weight", 0.2)),
        "heading": float(retrieval_cfg.get("heading_weight", 0.08)),
        "document_name": float(retrieval_cfg.get("document_name_weight", 0.12)),
        "intent": float(retrieval_cfg.get("intent_weight", 0.08)),
        "recency": float(retrieval_cfg.get("recency_weight", 0.03)),
        "source": float(retrieval_cfg.get("source_weight", 0.04)),
    }


def sync_command(root: Path, config: dict, config_file: Path) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    include_paths = [str(path) for path in config.get("include", [])]
    tracked_paths = {str(Path(path)) for path in include_paths}
    embedder = build_embedder(config)

    conn = connect(db_path)
    updated = 0
    skipped = 0
    removed = 0

    existing = {row["path"]: row for row in conn.execute("SELECT id, path, sha256 FROM documents")}
    stored_signature_row = conn.execute("SELECT value FROM meta WHERE key = 'index_signature'").fetchone()
    current_signature = config_signature(config)
    force_reindex = not stored_signature_row or stored_signature_row["value"] != current_signature

    for raw_path in include_paths:
        relative_path = str(Path(raw_path))
        absolute_path = resolve_path(root, raw_path)
        if not absolute_path.exists():
            continue

        text = absolute_path.read_text(encoding="utf-8")
        digest = sha256_text(text)
        document_row = existing.get(relative_path)
        if not force_reindex and document_row and document_row["sha256"] == digest:
            skipped += 1
            continue

        chunks = build_chunks(text, absolute_path, config)
        timestamp = utc_now()
        document_title = document_title_for_chunks(chunks, absolute_path, config)
        document_metadata = {
            "source_file": absolute_path.name,
            "document_kind": classify_document(absolute_path),
            "document_title": document_title,
            "mtime": dt.datetime.fromtimestamp(absolute_path.stat().st_mtime, tz=dt.timezone.utc).replace(microsecond=0).isoformat(),
        }
        with conn:
            if document_row:
                document_id = int(document_row["id"])
                conn.execute(
                    "UPDATE documents SET sha256 = ?, chunk_count = ?, updated_at = ?, kind = ?, metadata = ? WHERE id = ?",
                    (
                        digest,
                        len(chunks),
                        timestamp,
                        document_metadata["document_kind"],
                        json.dumps(document_metadata),
                        document_id,
                    ),
                )
                conn.execute("DELETE FROM chunks WHERE document_id = ?", (document_id,))
            else:
                cursor = conn.execute(
                    "INSERT INTO documents(path, sha256, chunk_count, updated_at, kind, metadata) VALUES (?, ?, ?, ?, ?, ?)",
                    (
                        relative_path,
                        digest,
                        len(chunks),
                        timestamp,
                        document_metadata["document_kind"],
                        json.dumps(document_metadata),
                    ),
                )
                document_id = int(cursor.lastrowid)

            for ordinal, chunk in enumerate(chunks):
                conn.execute(
                    """
                    INSERT INTO chunks(document_id, ordinal, heading, content, token_count, embedding, updated_at, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    """,
                    (
                        document_id,
                        ordinal,
                        chunk.heading,
                        chunk.content,
                        chunk.token_count,
                        json.dumps(embedder.embed(chunk.content)),
                        timestamp,
                        json.dumps(chunk.metadata),
                    ),
                )

        updated += 1

    stale_rows = conn.execute("SELECT id, path FROM documents").fetchall()
    for row in stale_rows:
        if row["path"] not in tracked_paths:
            with conn:
                conn.execute("DELETE FROM documents WHERE id = ?", (row["id"],))
            removed += 1

    with conn:
        conn.execute("INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)", ("config_path", display_path(config_file, root)))
        conn.execute("INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)", ("last_sync_at", utc_now()))
        conn.execute("INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)", ("embedding_provider", embedder.provider_name))
        conn.execute("INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)", ("index_signature", current_signature))

    total_docs = conn.execute("SELECT COUNT(*) FROM documents").fetchone()[0]
    total_chunks = conn.execute("SELECT COUNT(*) FROM chunks").fetchone()[0]
    print(f"Vector memory synced: db={db_path}")
    print(f"Provider: {embedder.provider_name}")
    if force_reindex:
        print("Reindexed because embedding/chunking settings changed or the index signature was missing.")
    print(f"Updated: {updated}  Skipped: {skipped}  Removed: {removed}")
    print(f"Documents: {total_docs}  Chunks: {total_chunks}")
    return 0


def cosine_similarity(query_vector: list[float], chunk_vector: list[float]) -> float:
    return sum(left * right for left, right in zip(query_vector, chunk_vector))


def overlap_score(query_terms: list[str], target_terms: list[str]) -> float:
    query_set = set(query_terms)
    target_set = set(target_terms)
    if not query_set or not target_set:
        return 0.0
    return len(query_set & target_set) / len(query_set)


def recency_score(date_hint: str | None, now: dt.datetime) -> float:
    if not date_hint:
        return 0.0
    try:
        parsed = dt.date.fromisoformat(date_hint)
    except ValueError:
        return 0.0
    days_old = max((now.date() - parsed).days, 0)
    return round(1.0 / (1.0 + (days_old / 120.0)), 6)


def preview(text: str, limit: int = 220) -> str:
    collapsed = " ".join(text.split())
    return collapsed if len(collapsed) <= limit else collapsed[: limit - 3] + "..."


def should_skip_hit(
    semantic: float,
    lexical: float,
    combined: float,
    content: str,
    document_name: float,
    intent: float,
    config: dict,
) -> bool:
    retrieval_cfg = config.get("retrieval", {})
    min_combined = float(retrieval_cfg.get("min_combined_score", 0.12))
    min_semantic_without_lexical = float(retrieval_cfg.get("min_semantic_without_lexical", 0.18))
    if combined < min_combined:
        return True
    if lexical == 0 and semantic < min_semantic_without_lexical and document_name == 0 and intent == 0:
        return True
    markers = [marker.lower() for marker in config.get("noise_markers", [])]
    lowered = content.lower()
    if any(marker in lowered for marker in markers) and lexical == 0 and intent == 0:
        return True
    return False


def score_hit(query_text: str, query_vector: list[float], row: sqlite3.Row, config: dict, strategy: str) -> dict | None:
    retrieval_cfg = config.get("retrieval", {})
    weights = retrieval_weights(config, strategy)
    query_terms = tokenize(query_text)
    content = row["content"]
    chunk_metadata = json.loads(row["chunk_metadata"] or "{}")
    document_metadata = json.loads(row["document_metadata"] or "{}")
    semantic_raw = cosine_similarity(query_vector, json.loads(row["embedding"]))
    semantic = max(semantic_raw, 0.0)
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
    combined = (
        semantic * weights["semantic"]
        + lexical * weights["lexical"]
        + heading * weights["heading"]
        + document_name * weights["document_name"]
        + intent * weights["intent"]
        + recency * weights["recency"]
        + source * weights["source"]
        - penalty
    )
    combined = round(combined, 6)

    if should_skip_hit(semantic, lexical, combined, content, document_name, intent, config):
        return None

    return {
        "score": combined,
        "path": row["path"],
        "ordinal": row["ordinal"],
        "heading": row["heading"] or "Document",
        "preview": preview(content, limit=int(retrieval_cfg.get("preview_chars", 220))),
        "components": {
            "semantic": round(semantic, 6),
            "lexical": round(lexical, 6),
            "heading": round(heading, 6),
            "document_name": round(document_name, 6),
            "intent": round(intent, 6),
            "recency": round(recency, 6),
            "source": round(source, 6),
            "penalty": round(penalty, 6),
        },
        "metadata": {
            "document_kind": row["document_kind"],
            "source_file": Path(row["path"]).name,
            "structure": chunk_metadata.get("structure", "text"),
            "date_hint": chunk_metadata.get("date_hint"),
            "document_title": document_metadata.get("document_title"),
        },
    }


def query_command(root: Path, config: dict, query_text: str, limit: int, json_output: bool, strategy: str) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    if not db_path.exists():
        print(f"Vector memory missing: {db_path}. Run sync first.", file=sys.stderr)
        return 1

    conn = connect(db_path)
    embedder = build_embedder(config)
    query_vector = embedder.embed(query_text)
    rows = conn.execute(
        """
        SELECT
          documents.path,
          documents.kind AS document_kind,
          documents.metadata AS document_metadata,
          chunks.ordinal,
          chunks.heading,
          chunks.content,
          chunks.embedding,
          chunks.metadata AS chunk_metadata
        FROM chunks
        JOIN documents ON documents.id = chunks.document_id
        """
    ).fetchall()

    scored: list[dict] = []
    for row in rows:
        hit = score_hit(query_text, query_vector, row, config, strategy)
        if hit:
            scored.append(hit)

    scored.sort(
        key=lambda item: (
            item["score"],
            item["components"]["intent"],
            item["components"]["document_name"],
            item["components"]["heading"],
            item["components"]["lexical"],
            item["components"]["source"],
            item["components"]["semantic"],
            -item["ordinal"],
        ),
        reverse=True,
    )

    max_per_document = int(config.get("retrieval", {}).get("max_per_document", 2))
    top_hits: list[dict] = []
    per_document: dict[str, int] = {}
    for hit in scored:
        if per_document.get(hit["path"], 0) >= max_per_document:
            continue
        top_hits.append(hit)
        per_document[hit["path"]] = per_document.get(hit["path"], 0) + 1
        if len(top_hits) >= limit:
            break

    if json_output:
        print(json.dumps(top_hits, indent=2))
        return 0

    if not top_hits:
        print("No vector hits found.")
        return 0

    for index, hit in enumerate(top_hits, start=1):
        components = hit["components"]
        print(
            f"#{index} score={hit['score']} path={hit['path']} heading={hit['heading']} chunk={hit['ordinal']} "
            f"(semantic={components['semantic']} lexical={components['lexical']} heading={components['heading']} "
            f"doc={components['document_name']} intent={components['intent']} penalty={components['penalty']})"
        )
        print(f"    {hit['preview']}")
    return 0


def status_command(root: Path, config: dict, config_file: Path) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    if not db_path.exists():
        print(f"Vector memory not initialized. Config: {display_path(config_file, root)}")
        return 0

    conn = connect(db_path)
    total_docs = conn.execute("SELECT COUNT(*) FROM documents").fetchone()[0]
    total_chunks = conn.execute("SELECT COUNT(*) FROM chunks").fetchone()[0]
    last_sync = conn.execute("SELECT value FROM meta WHERE key = 'last_sync_at'").fetchone()
    provider = conn.execute("SELECT value FROM meta WHERE key = 'embedding_provider'").fetchone()

    retrieval_cfg = config.get("retrieval", {})

    print(f"DB: {db_path}")
    print(f"Config: {display_path(config_file, root)}")
    print(f"Provider: {provider['value'] if provider else build_embedder(config).provider_name}")
    print(f"Documents: {total_docs}")
    print(f"Chunks: {total_chunks}")
    print(f"Last sync: {last_sync['value'] if last_sync else 'unknown'}")
    print("Retrieval weights:")
    print(
        "  semantic={semantic} lexical={lexical} heading={heading} document_name={document_name} intent={intent} recency={recency} source={source}".format(
            semantic=retrieval_cfg.get("semantic_weight", 0.45),
            lexical=retrieval_cfg.get("lexical_weight", 0.2),
            heading=retrieval_cfg.get("heading_weight", 0.08),
            document_name=retrieval_cfg.get("document_name_weight", 0.12),
            intent=retrieval_cfg.get("intent_weight", 0.08),
            recency=retrieval_cfg.get("recency_weight", 0.03),
            source=retrieval_cfg.get("source_weight", 0.04),
        )
    )
    print("Indexed files:")
    for raw_path in config.get("include", []):
        print(f"  - {raw_path}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="BosskuAI sqlite-backed local-first memory retrieval."
    )
    parser.add_argument(
        "--root",
        default=str(repo_root()),
        help="Workspace root. Defaults to the current BosskuAI repo root.",
    )
    parser.add_argument(
        "--config",
        default=None,
        help="Optional path to vector-config.json.",
    )

    subparsers = parser.add_subparsers(dest="command", required=True)

    subparsers.add_parser("sync", help="Index configured memory files into the vector store.")

    query_parser = subparsers.add_parser("query", help="Query semantic memory.")
    query_parser.add_argument("text", help="Natural-language query.")
    query_parser.add_argument("--limit", type=int, default=5, help="Max hits to return.")
    query_parser.add_argument(
        "--strategy",
        choices=["hybrid", "semantic-only"],
        default="hybrid",
        help="Ranking strategy. 'hybrid' is the default local BosskuAI scorer; 'semantic-only' is a baseline approximation.",
    )
    query_parser.add_argument("--json", action="store_true", help="Emit JSON instead of text.")

    subparsers.add_parser("status", help="Show vector memory status.")
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()

    root = Path(args.root).expanduser().resolve()
    config, config_file = load_config(root, args.config)

    if args.command == "sync":
        return sync_command(root, config, config_file)
    if args.command == "query":
        return query_command(
            root,
            config,
            query_text=args.text,
            limit=args.limit,
            json_output=args.json,
            strategy=args.strategy,
        )
    if args.command == "status":
        return status_command(root, config, config_file)

    parser.error(f"Unknown command: {args.command}")
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
