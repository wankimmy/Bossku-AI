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
from pathlib import Path

TOKEN_RE = re.compile(r"[A-Za-z0-9_./:-]{2,}")
HEADING_RE = re.compile(r"^(#{1,6})\s+(.*)$")


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


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def sha256_text(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


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
          updated_at TEXT NOT NULL
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
          UNIQUE(document_id, ordinal)
        );

        CREATE INDEX IF NOT EXISTS idx_chunks_document_id ON chunks(document_id);
        """
    )


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


def embed_local_hash(text: str, dimensions: int, seed: str) -> list[float]:
    vector = [0.0] * dimensions
    for term in tokenize(text):
        digest = hashlib.sha256(f"{seed}:{term}".encode("utf-8")).digest()
        index = int.from_bytes(digest[:4], "big") % dimensions
        sign = 1.0 if digest[4] % 2 == 0 else -1.0
        weight = 1.0 + min(len(term), 24) / 24.0
        vector[index] += sign * weight

    norm = math.sqrt(sum(value * value for value in vector))
    if norm == 0:
        return vector
    return [round(value / norm, 8) for value in vector]


def embed_text(text: str, config: dict) -> list[float]:
    embedding_cfg = config.get("embedding", {})
    provider = embedding_cfg.get("provider", "local-hash")
    if provider != "local-hash":
        raise ValueError(f"Unsupported embedding provider '{provider}'. Supported: local-hash.")
    dimensions = int(embedding_cfg.get("dimensions", 256))
    seed = str(embedding_cfg.get("seed", "bosskuai-vector-v1"))
    return embed_local_hash(text, dimensions, seed)


def split_sections(text: str) -> list[tuple[str, str]]:
    sections: list[tuple[str, str]] = []
    current_heading = "Document"
    current_lines: list[str] = []

    for line in text.splitlines():
        heading_match = HEADING_RE.match(line.strip())
        if heading_match and current_lines:
            body = "\n".join(current_lines).strip()
            if body:
                sections.append((current_heading, body))
            current_heading = heading_match.group(2).strip()
            current_lines = [line]
        else:
            current_lines.append(line)

    trailing = "\n".join(current_lines).strip()
    if trailing:
        sections.append((current_heading, trailing))
    return sections or [("Document", text.strip())]


def chunk_text(text: str, max_chars: int, overlap_chars: int) -> list[str]:
    clean = re.sub(r"\n{3,}", "\n\n", text.strip())
    if not clean:
        return []

    chunks: list[str] = []
    start = 0
    text_length = len(clean)

    while start < text_length:
        end = min(text_length, start + max_chars)
        if end < text_length:
            boundary = clean.rfind("\n\n", start + max_chars // 2, end)
            if boundary == -1:
                boundary = clean.rfind("\n", start + max_chars // 2, end)
            if boundary > start:
                end = boundary

        piece = clean[start:end].strip()
        if piece:
            chunks.append(piece)

        if end >= text_length:
            break

        next_start = max(0, end - overlap_chars)
        start = next_start if next_start > start else end

    return chunks


def build_chunks(text: str, config: dict) -> list[dict]:
    chunk_cfg = config.get("chunking", {})
    max_chars = int(chunk_cfg.get("max_chars", 900))
    overlap_chars = int(chunk_cfg.get("overlap_chars", 120))
    built: list[dict] = []

    for heading, section_text in split_sections(text):
        for content in chunk_text(section_text, max_chars=max_chars, overlap_chars=overlap_chars):
            built.append(
                {
                    "heading": heading,
                    "content": content,
                    "token_count": len(tokenize(content)),
                }
            )
    return built


def sync_command(root: Path, config: dict, config_file: Path) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    include_paths = [str(path) for path in config.get("include", [])]
    tracked_paths = {str(Path(path)) for path in include_paths}

    conn = connect(db_path)
    updated = 0
    skipped = 0
    removed = 0

    existing = {
        row["path"]: row
        for row in conn.execute("SELECT id, path, sha256 FROM documents")
    }

    for raw_path in include_paths:
        relative_path = str(Path(raw_path))
        absolute_path = resolve_path(root, raw_path)
        if not absolute_path.exists():
            continue

        text = absolute_path.read_text(encoding="utf-8")
        digest = sha256_text(text)
        document_row = existing.get(relative_path)
        if document_row and document_row["sha256"] == digest:
            skipped += 1
            continue

        chunks = build_chunks(text, config)
        timestamp = utc_now()
        with conn:
            if document_row:
                document_id = int(document_row["id"])
                conn.execute(
                    "UPDATE documents SET sha256 = ?, chunk_count = ?, updated_at = ? WHERE id = ?",
                    (digest, len(chunks), timestamp, document_id),
                )
                conn.execute("DELETE FROM chunks WHERE document_id = ?", (document_id,))
            else:
                cursor = conn.execute(
                    "INSERT INTO documents(path, sha256, chunk_count, updated_at) VALUES (?, ?, ?, ?)",
                    (relative_path, digest, len(chunks), timestamp),
                )
                document_id = int(cursor.lastrowid)

            for ordinal, chunk in enumerate(chunks):
                conn.execute(
                    """
                    INSERT INTO chunks(document_id, ordinal, heading, content, token_count, embedding, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    """,
                    (
                        document_id,
                        ordinal,
                        chunk["heading"],
                        chunk["content"],
                        chunk["token_count"],
                        json.dumps(embed_text(chunk["content"], config)),
                        timestamp,
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
        conn.execute(
            "INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)",
            ("config_path", str(config_file.relative_to(root))),
        )
        conn.execute(
            "INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)",
            ("last_sync_at", utc_now()),
        )

    total_docs = conn.execute("SELECT COUNT(*) FROM documents").fetchone()[0]
    total_chunks = conn.execute("SELECT COUNT(*) FROM chunks").fetchone()[0]
    print(f"Vector memory synced: db={db_path}")
    print(f"Updated: {updated}  Skipped: {skipped}  Removed: {removed}")
    print(f"Documents: {total_docs}  Chunks: {total_chunks}")
    return 0


def cosine_similarity(query_vector: list[float], chunk_vector: list[float]) -> float:
    return sum(left * right for left, right in zip(query_vector, chunk_vector))


def preview(text: str, limit: int = 200) -> str:
    collapsed = " ".join(text.split())
    return collapsed if len(collapsed) <= limit else collapsed[: limit - 3] + "..."


def query_command(root: Path, config: dict, query_text: str, limit: int, json_output: bool) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    if not db_path.exists():
        print(f"Vector memory missing: {db_path}. Run sync first.", file=sys.stderr)
        return 1

    conn = connect(db_path)
    query_vector = embed_text(query_text, config)
    rows = conn.execute(
        """
        SELECT documents.path, chunks.ordinal, chunks.heading, chunks.content, chunks.embedding
        FROM chunks
        JOIN documents ON documents.id = chunks.document_id
        """
    ).fetchall()

    scored = []
    for row in rows:
        score = cosine_similarity(query_vector, json.loads(row["embedding"]))
        scored.append(
            {
                "score": round(score, 6),
                "path": row["path"],
                "ordinal": row["ordinal"],
                "heading": row["heading"] or "Document",
                "preview": preview(row["content"]),
            }
        )

    scored.sort(key=lambda item: item["score"], reverse=True)
    top_hits = scored[:limit]

    if json_output:
        print(json.dumps(top_hits, indent=2))
        return 0

    if not top_hits:
        print("No vector hits found.")
        return 0

    for index, hit in enumerate(top_hits, start=1):
        print(f"#{index} score={hit['score']} path={hit['path']} heading={hit['heading']} chunk={hit['ordinal']}")
        print(f"    {hit['preview']}")
    return 0


def status_command(root: Path, config: dict, config_file: Path) -> int:
    db_path = resolve_path(root, config.get("database_path", "ai-assistant/memory/semantic-memory.sqlite3"))
    if not db_path.exists():
        print(f"Vector memory not initialized. Config: {config_file.relative_to(root)}")
        return 0

    conn = connect(db_path)
    total_docs = conn.execute("SELECT COUNT(*) FROM documents").fetchone()[0]
    total_chunks = conn.execute("SELECT COUNT(*) FROM chunks").fetchone()[0]
    last_sync = conn.execute("SELECT value FROM meta WHERE key = 'last_sync_at'").fetchone()

    print(f"DB: {db_path}")
    print(f"Config: {config_file.relative_to(root)}")
    print(f"Documents: {total_docs}")
    print(f"Chunks: {total_chunks}")
    print(f"Last sync: {last_sync['value'] if last_sync else 'unknown'}")
    print("Indexed files:")
    for raw_path in config.get("include", []):
        print(f"  - {raw_path}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="BosskuAI sqlite-backed vector memory for long-term workspace recall."
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
        return query_command(root, config, query_text=args.text, limit=args.limit, json_output=args.json)
    if args.command == "status":
        return status_command(root, config, config_file)

    parser.error(f"Unknown command: {args.command}")
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
