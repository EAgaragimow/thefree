<?php

namespace App\Actions\Profiles;

/**
 * Turns a pasted YouTube, Vimeo or Loom link into the URL of its embeddable player. Anything
 * else (other hosts, other schemes, malformed ids) is rejected, so profiles can only ever
 * embed these three providers.
 */
class VideoEmbed
{
    public static function embedUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || ! isset($parts['host'])) {
            return null;
        }

        $host = preg_replace('/^(www|m)\./i', '', strtolower($parts['host']));
        $path = $parts['path'] ?? '';

        return match (true) {
            $host === 'youtu.be' => self::youtube(ltrim($path, '/')),
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) => self::youtubeFromPath($path, $parts['query'] ?? ''),
            $host === 'vimeo.com' => self::vimeo($path, '#^/(?:channels/[^/]+/)?(\d+)(?:/[A-Za-z0-9]+)?/?$#'),
            $host === 'player.vimeo.com' => self::vimeo($path, '#^/video/(\d+)/?$#'),
            $host === 'loom.com' => self::loom($path),
            default => null,
        };
    }

    public static function isSupported(string $url): bool
    {
        return self::embedUrl($url) !== null;
    }

    private static function youtubeFromPath(string $path, string $query): ?string
    {
        if ($path === '/watch') {
            parse_str($query, $params);

            return self::youtube((string) ($params['v'] ?? ''));
        }

        if (preg_match('#^/(?:embed|shorts|live)/([^/?]+)#', $path, $matches) === 1) {
            return self::youtube($matches[1]);
        }

        return null;
    }

    private static function youtube(string $id): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1
            ? "https://www.youtube-nocookie.com/embed/{$id}"
            : null;
    }

    private static function vimeo(string $path, string $pattern): ?string
    {
        return preg_match($pattern, $path, $matches) === 1
            ? "https://player.vimeo.com/video/{$matches[1]}"
            : null;
    }

    private static function loom(string $path): ?string
    {
        return preg_match('#^/(?:share|embed)/([a-f0-9]{32})/?$#i', $path, $matches) === 1
            ? 'https://www.loom.com/embed/'.strtolower($matches[1])
            : null;
    }
}
