<?php

namespace App\Services\BosskuAi\Plans;

/**
 * A single revision of a RevisionedDocument. Immutable once created.
 */
final readonly class Revision
{
    public function __construct(
        public readonly string $id,
        public readonly string $documentKey,
        public readonly int $number,
        public readonly string $content,
        public readonly string $author,
        public readonly ?string $createdAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'document_key' => $this->documentKey,
            'number' => $this->number,
            'content' => $this->content,
            'author' => $this->author,
            'created_at' => $this->createdAt,
        ];
    }
}