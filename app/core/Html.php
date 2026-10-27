<?php
declare(strict_types=1);
class Html {
    public static function clean(string $html): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $old = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($old);
        $root = $doc->getElementById('root');
        if (!$root) return '';
        $allowed = ['p','h2','h3','h4','ul','ol','li','strong','b','em','i','a','img','blockquote','br','table','thead','tbody','tr','th','td'];
        $walk = static function (DOMNode $node) use (&$walk, $allowed): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof DOMComment) { $node->removeChild($child); continue; }
                if (!$child instanceof DOMElement) continue;
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script','style','iframe','object','embed','svg','math','form','input','button'], true)) { $node->removeChild($child); continue; }
                $walk($child);
                if (!in_array($tag, $allowed, true)) { while ($child->firstChild) $node->insertBefore($child->firstChild, $child); $node->removeChild($child); continue; }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name); $value = $attr->value;
                    $safe = ($tag === 'a' && $name === 'href' && preg_match('~^(https?://|/(?!/)|#)~i', $value))
                        || ($tag === 'img' && $name === 'src' && preg_match('~^/(uploads|assets/images)/[a-zA-Z0-9_./-]+\.(jpg|jpeg|png|webp)$~', $value) && !str_contains($value, '..'))
                        || (in_array($name, ['alt','title'], true))
                        || ($tag === 'img' && in_array($name, ['width','height'], true) && ctype_digit($value) && (int)$value <= 2000);
                    if (!$safe) $child->removeAttributeNode($attr);
                }
                if ($tag === 'img' && !$child->hasAttribute('src')) $node->removeChild($child);
            }
        };
        $walk($root); $result = '';
        foreach ($root->childNodes as $child) $result .= $doc->saveHTML($child);
        return $result;
    }
}
