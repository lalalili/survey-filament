export interface ParsedVideo {
  provider: 'youtube' | 'vimeo';
  src: string;
}

/**
 * Converts a YouTube or Vimeo URL to an embeddable iframe src.
 * Returns null for unsupported URLs.
 */
export function parseVideoUrl(url: string): ParsedVideo | null {
  const trimmed = url.trim();
  if (!trimmed) return null;

  // YouTube: youtube.com/watch?v=ID or youtu.be/ID or youtube.com/shorts/ID
  const ytMatch =
    trimmed.match(/(?:youtube\.com\/watch[?&]v=|youtu\.be\/|youtube\.com\/shorts\/)([A-Za-z0-9_-]{11})/) ??
    trimmed.match(/youtube\.com\/embed\/([A-Za-z0-9_-]{11})/);

  if (ytMatch) {
    return {
      provider: 'youtube',
      src: `https://www.youtube.com/embed/${ytMatch[1]}?rel=0`,
    };
  }

  // Vimeo: vimeo.com/ID or player.vimeo.com/video/ID
  const vimeoMatch =
    trimmed.match(/vimeo\.com\/(\d+)/) ??
    trimmed.match(/player\.vimeo\.com\/video\/(\d+)/);

  if (vimeoMatch) {
    return {
      provider: 'vimeo',
      src: `https://player.vimeo.com/video/${vimeoMatch[1]}`,
    };
  }

  return null;
}
