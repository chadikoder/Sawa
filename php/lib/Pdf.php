<?php
declare(strict_types=1);

/**
 * A very small PDF writer — just enough to lay out a receipt.
 *
 * The Download PDF button used to send a .txt file with a PDF label on it,
 * which is not something a donor can file or hand to an accountant.
 *
 * Why hand-rolled rather than FPDF/TCPDF/Dompdf: this project is submitted as
 * a folder that a marker unzips and runs under XAMPP. There is no composer
 * install step, no vendor/ directory, and no guarantee of an internet
 * connection on the machine it is opened on. A dependency would have to be
 * committed as a binary blob nobody in the repo can read. Everything a receipt
 * needs — left/right aligned text in two weights, rules, and filled boxes — is
 * a few hundred lines against the PDF 1.4 spec, using only the two Base-14
 * fonts every viewer is required to have built in. No fonts are embedded, no
 * extensions are required, and the output opens in Acrobat, Preview, Chrome
 * and Edge.
 *
 * Deliberately not supported: images, multiple pages, compression, unicode.
 * A receipt is one page of Latin text; anything more belongs in a real library.
 */
final class Pdf
{
    /** A4 in PostScript points (1/72"). */
    public const WIDTH  = 595.28;
    public const HEIGHT = 841.89;

    public const REGULAR = 'F1';
    public const BOLD    = 'F2';

    /** Content-stream operators, in drawing order. */
    private string $buffer = '';

    /**
     * Advance widths for the two fonts, in 1/1000 em, for ASCII 32..126.
     *
     * Needed for right-alignment: money columns in a receipt have to line up
     * on their last digit, and without metrics the only options are guessing
     * (ragged) or a monospace font (ugly). These are the standard Adobe
     * Helvetica / Helvetica-Bold values, which is what a viewer uses when it
     * substitutes its own copy of the font.
     */
    private const W_REGULAR = '278 278 355 556 556 889 667 191 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 278 278 584 584 584 556 1015 667 667 722 722 667 611 778 722 278 500 667 556 833 722 778 667 778 722 667 611 722 667 944 667 667 611 278 278 278 469 556 333 556 556 500 556 556 278 556 556 222 222 500 222 833 556 556 556 556 333 500 278 556 500 722 500 500 500 334 260 334 584';
    private const W_BOLD    = '278 333 474 556 556 889 722 238 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 333 333 584 584 584 611 975 722 722 722 722 667 611 778 722 278 556 722 611 833 722 778 667 778 722 667 611 722 667 944 667 667 611 333 278 333 584 556 333 556 611 556 611 556 333 611 611 278 278 556 278 889 611 611 611 611 389 556 333 611 556 778 556 556 500 389 280 389 584';

    /** @var array<string, list<int>> */
    private static array $widths = [];

    /**
     * Width of $text at $size, in points.
     *
     * Callers use this to right-align or centre. Characters outside the table
     * fall back to the width of a space, which keeps the arithmetic honest for
     * anything that slipped through sanitisation.
     */
    public static function widthOf(string $text, float $size, string $font = self::REGULAR): float
    {
        if (self::$widths === []) {
            self::$widths = [
                self::REGULAR => array_map('intval', explode(' ', self::W_REGULAR)),
                self::BOLD    => array_map('intval', explode(' ', self::W_BOLD)),
            ];
        }
        $table = self::$widths[$font] ?? self::$widths[self::REGULAR];
        $total = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($text[$i]);
            $total += ($code >= 32 && $code <= 126) ? $table[$code - 32] : $table[0];
        }
        return ($total / 1000) * $size;
    }

    /**
     * Draw a string with its baseline at $y measured DOWN from the top of the
     * page. PDF's own origin is the bottom-left corner, which is unusable for
     * laying a document out top to bottom, so the flip happens here once.
     */
    public function text(
        float $x,
        float $y,
        string $text,
        float $size = 10,
        string $font = self::REGULAR,
        array $rgb = [0, 0, 0]
    ): void {
        $this->buffer .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $font,
            $size,
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $x,
            self::HEIGHT - $y,
            self::escape($text)
        );
    }

    /** Same, but the string ENDS at $x — for money columns. */
    public function textRight(
        float $x,
        float $y,
        string $text,
        float $size = 10,
        string $font = self::REGULAR,
        array $rgb = [0, 0, 0]
    ): void {
        $this->text($x - self::widthOf($text, $size, $font), $y, $text, $size, $font, $rgb);
    }

    /** Horizontal or diagonal rule. */
    public function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.6, array $rgb = [200, 210, 222]): void
    {
        $this->buffer .= sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $w,
            $x1,
            self::HEIGHT - $y1,
            $x2,
            self::HEIGHT - $y2
        );
    }

    /** Filled rectangle, $y being its TOP edge. */
    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->buffer .= sprintf(
            "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            $rgb[0] / 255,
            $rgb[1] / 255,
            $rgb[2] / 255,
            $x,
            self::HEIGHT - $y - $h,
            $w,
            $h
        );
    }

    /**
     * Wrap $text to $maxWidth and draw it, returning the y after the last line.
     * Long free text (a campaign title, a footer note) would otherwise run off
     * the right edge of the page — there is no reflow in a PDF.
     */
    public function paragraph(
        float $x,
        float $y,
        float $maxWidth,
        string $text,
        float $size = 9,
        float $leading = 12,
        string $font = self::REGULAR,
        array $rgb = [71, 85, 105]
    ): float {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $line = '';
        foreach ($words as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if (self::widthOf($try, $size, $font) > $maxWidth && $line !== '') {
                $this->text($x, $y, $line, $size, $font, $rgb);
                $y += $leading;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $this->text($x, $y, $line, $size, $font, $rgb);
            $y += $leading;
        }
        return $y;
    }

    /** The finished file, ready to echo. */
    public function output(): string
    {
        $stream = $this->buffer;

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] "
                . "/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>",
                self::WIDTH,
                self::HEIGHT
            ),
            4 => "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            6 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }

        // The cross-reference table is byte offsets into the file, so it can
        // only be written once every object above is in its final position.
        $xrefAt = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 " . $count . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xrefAt . "\n%%EOF";

        return $pdf;
    }

    /**
     * Escape a string for a PDF literal and drop anything that is not printable
     * ASCII. Parentheses and backslashes end or escape a string literal, so an
     * unescaped campaign title containing "(" would corrupt the whole file —
     * and campaign titles are user input.
     */
    private static function escape(string $text): string
    {
        $text = (string) preg_replace('/[^\x20-\x7E]/', '', $text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
