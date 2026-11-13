<?php

interface ProviderInterface {
    public function sendText(string $to, string $text): array;
    public function sendMedia(string $to, string $filePath, string $mime, string $caption = '', string $fileName = ''): array;
    public function downloadMedia(array $media): array;
    public function sendTemplate(string $to, string $template, string $language, array $params = []): array;
    public function markRead(string $providerMessageId): void;
    public function verifyWebhook(array $headers, string $rawBody): bool;
    public function parseWebhook(array $payload): array;
    public function testConnection(): array;
    public function getQrCode(): array;
    public function getConnectionState(): array;
    public function getTemplates(): array;
}
