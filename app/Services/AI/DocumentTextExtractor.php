<?php

namespace App\Services\AI;

class DocumentTextExtractor
{
    private const MAX_CHARS = 60000;

    public function extract(string $binary, string $filename): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $magic = substr($binary, 0, 8);

        if (str_starts_with($magic, "PK\x03\x04")) {
            $text = $this->fromZip($binary, $extension);
        } elseif (str_starts_with($magic, "\xD0\xCF\x11\xE0")) {
            $text = $this->fromOle($binary);
        } elseif (str_starts_with($binary, '{\\rtf')) {
            $text = $this->fromRtf($binary);
        } else {
            $text = $this->fromPlainText($binary);
        }

        $text = $this->normalize($text);
        return mb_substr($text, 0, self::MAX_CHARS);
    }

    private function fromZip(string $binary, string $extension): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }

        $temp = tempnam(sys_get_temp_dir(), 'docx_');
        if ($temp === false) {
            return '';
        }
        file_put_contents($temp, $binary);

        $zip = new \ZipArchive();
        if ($zip->open($temp) !== true) {
            @unlink($temp);
            return '';
        }

        $patterns = $this->patternsFor($zip, $extension);
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            foreach ($patterns as $pattern) {
                if (!preg_match($pattern, $name)) {
                    continue;
                }
                $content = $zip->getFromIndex($i);
                if (is_string($content) && $content !== '') {
                    $parts[] = $this->xmlToText($content);
                }
                break;
            }
        }
        $zip->close();
        @unlink($temp);

        return implode("\n", array_filter($parts, fn ($part) => trim($part) !== ''));
    }

    private function patternsFor(\ZipArchive $zip, string $extension): array
    {
        $has = fn (string $name): bool => $zip->locateName($name) !== false;

        if ($extension === 'docx' || $has('word/document.xml')) {
            return [
                '#^word/document\.xml$#',
                '#^word/(?:header|footer)\d*\.xml$#',
                '#^word/footnotes\.xml$#',
                '#^word/endnotes\.xml$#',
            ];
        }
        if ($extension === 'xlsx' || $has('xl/sharedStrings.xml')) {
            return [
                '#^xl/sharedStrings\.xml$#',
                '#^xl/worksheets/sheet\d+\.xml$#',
            ];
        }
        if ($extension === 'pptx' || $has('ppt/presentation.xml')) {
            return [
                '#^ppt/slides/slide\d+\.xml$#',
                '#^ppt/notesSlides/notesSlide\d+\.xml$#',
            ];
        }
        if ($extension === 'odt' || $has('content.xml')) {
            return ['#^content\.xml$#'];
        }

        return ['#^word/document\.xml$#', '#^content\.xml$#'];
    }

    private function xmlToText(string $xml): string
    {
        $text = preg_replace('#</(?:w:p|w:tr|a:p|text:p|text:h|si|row)>#i', "\n", $xml);
        $text = (string) $text;
        $text = preg_replace('#<w:tab\s*/>#i', "\t", $text);
        $text = strip_tags($text);
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function fromRtf(string $rtf): string
    {
        $text = preg_replace_callback("/\\\\'([0-9a-fA-F]{2})/", fn ($m) => chr((int) hexdec($m[1])), $rtf);
        $text = (string) $text;
        $text = preg_replace('/\\\\par[d]?\b/', "\n", $text);
        $text = preg_replace('/\\\\line\b/', "\n", $text);
        $text = preg_replace('/\\\\tab\b/', "\t", $text);
        $text = preg_replace('/\\\\\*?[a-zA-Z]+-?\d* ?/', '', $text);
        $text = preg_replace('/[{}]/', '', $text);
        return (string) $text;
    }

    private function fromOle(string $binary): string
    {
        $parts = [];

        if (preg_match_all('/(?:[\x09\x0A\x0D\x20-\x7E]\x00){4,}/', $binary, $matches)) {
            foreach ($matches[0] as $chunk) {
                $parts[] = mb_convert_encoding($chunk, 'UTF-8', 'UTF-16LE');
            }
        }

        if (preg_match_all('/[\x20-\x7E\xA0-\xFF]{6,}/', $binary, $matches)) {
            foreach ($matches[0] as $chunk) {
                $parts[] = mb_convert_encoding($chunk, 'UTF-8', 'Windows-1252');
            }
        }

        return implode("\n", $parts);
    }

    private function fromPlainText(string $binary): string
    {
        if (!mb_check_encoding($binary, 'UTF-8')) {
            return (string) mb_convert_encoding($binary, 'UTF-8', 'Windows-1252');
        }
        return $binary;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00a0}"], ["\n", "\n", ' '], $text);
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
