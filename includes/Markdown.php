<?php

namespace Phoenix;

class Markdown
{
    public static function toHtml(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);

        // Fenced code blocks (``` ... ```)
        $text = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) {
            $code = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            return '<pre class="code-block"><code>' . $code . '</code></pre>';
        }, $text);

        // Inline code
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            return '<code style="background:var(--bg-input);padding:2px 6px;border-radius:4px;font-size:13px">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
        }, $text);

        // Horizontal rules
        $text = preg_replace('/^---+$/m', '<hr style="border:none;border-top:1px solid var(--border);margin:24px 0">', $text);

        // Headings
        $text = preg_replace_callback('/^(#{1,4})\s+(.+)$/m', function ($m) {
            $level = strlen($m[1]);
            $id = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($m[2])));
            $sizes = [1 => '28px', 2 => '22px', 3 => '18px', 4 => '15px'];
            $margins = [1 => '32px 0 16px', 2 => '28px 0 12px', 3 => '24px 0 8px', 4 => '20px 0 8px'];
            $size = $sizes[$level] ?? '15px';
            $margin = $margins[$level] ?? '20px 0 8px';
            return "<h{$level} id=\"{$id}\" style=\"font-size:{$size};font-weight:700;margin:{$margin}\">{$m[2]}</h{$level}>";
        }, $text);

        // Images
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;border-radius:var(--radius-sm);margin:8px 0">', $text);

        // Links
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" style="color:var(--accent)">$1</a>', $text);

        // Bold and italic
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);

        // Blockquotes (multi-line support)
        $text = preg_replace_callback('/(?:^>\s?.+\n?)+/m', function ($m) {
            $content = preg_replace('/^>\s?/m', '', $m[0]);
            $style = 'border-left:3px solid var(--accent);padding:12px 16px;margin:12px 0;background:var(--accent-glow);border-radius:0 var(--radius-sm) var(--radius-sm) 0;font-size:14px;color:var(--text-secondary)';
            if (stripos($content, 'Warning:') === 0 || stripos($content, '⚠') === 0) {
                $style = str_replace('var(--accent)', 'var(--danger)', $style);
                $style = str_replace('var(--accent-glow)', 'rgba(239,68,68,0.1)', $style);
            }
            return "<blockquote style=\"{$style}\">" . trim($content) . "</blockquote>";
        }, $text);

        // Unordered lists
        $text = preg_replace_callback('/(?:^[-*]\s+.+\n?)+/m', function ($m) {
            $items = preg_split('/^[-*]\s+/m', $m[0], -1, PREG_SPLIT_NO_EMPTY);
            $html = '<ul style="margin:12px 0;padding-left:24px">';
            foreach ($items as $item) {
                $html .= '<li style="margin-bottom:4px;font-size:14px;line-height:1.6">' . trim($item) . '</li>';
            }
            return $html . '</ul>';
        }, $text);

        // Ordered lists
        $text = preg_replace_callback('/(?:^\d+\.\s+.+\n?)+/m', function ($m) {
            $items = preg_split('/^\d+\.\s+/m', $m[0], -1, PREG_SPLIT_NO_EMPTY);
            $html = '<ol style="margin:12px 0;padding-left:24px">';
            foreach ($items as $item) {
                $html .= '<li style="margin-bottom:6px;font-size:14px;line-height:1.6">' . trim($item) . '</li>';
            }
            return $html . '</ol>';
        }, $text);

        // Paragraphs
        $blocks = preg_split('/\n{2,}/', $text);
        $result = '';
        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;
            if (preg_match('/^<(h[1-4]|ul|ol|pre|blockquote|hr|img|div)/', $block)) {
                $result .= $block . "\n";
            } else {
                $block = str_replace("\n", '<br>', $block);
                $result .= '<p style="font-size:14px;line-height:1.7;color:var(--text-secondary);margin-bottom:12px">' . $block . '</p>' . "\n";
            }
        }

        return $result;
    }

    public static function extractTitle(string $text): string
    {
        if (preg_match('/^#\s+(.+)$/m', $text, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
