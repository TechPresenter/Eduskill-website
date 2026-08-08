<?php
/**
 * =============================================================================
 *  PWF_QR — self-contained QR Code encoder (no Composer, no external service)
 * =============================================================================
 *  Byte (8-bit) mode, error-correction level L/M/Q/H, versions 1..10 (enough
 *  for a verification URL). Implements Reed-Solomon over GF(256), the full set
 *  of function patterns, and all-8-mask penalty selection — closely following
 *  the well-known reference algorithm by Project Nayuki (MIT).
 *
 *  Coordinate convention mirrors the reference: modules[$y][$x] with $x = column
 *  and $y = row, so the placement maths can be copied verbatim.
 *
 *  Usage:
 *      require_once __DIR__ . '/lib/qrcode.php';
 *      $qr = PWF_QR::encode('https://example.org/verify?token=abc', 'M');
 *      echo $qr->svg(4, 6);                 // quiet zone 4, 6px/module
 *      $matrix = $qr->matrix();             // 2D array of 0/1, no quiet zone
 * =============================================================================
 */

declare(strict_types=1);

final class PWF_QR
{
    /** @var int[][] modules[y][x] = 0|1 (no quiet zone) */
    public array $modules;
    public int $size;
    public int $version;
    public string $ecLevel;

    /* GF(256) tables (primitive polynomial 0x11d). */
    private static ?array $exp = null;
    private static ?array $log = null;

    /**
     * Error-correction characteristics for versions 1..10.
     * [ecPerBlock, group1Blocks, group1DataCW, group2Blocks, group2DataCW]
     * Verified: sum(blocks * (data + ec)) == total codewords for each version.
     */
    private const ECC = [
        1  => ['L' => [7,1,19,0,0],  'M' => [10,1,16,0,0],  'Q' => [13,1,13,0,0],  'H' => [17,1,9,0,0]],
        2  => ['L' => [10,1,34,0,0], 'M' => [16,1,28,0,0],  'Q' => [22,1,22,0,0],  'H' => [28,1,16,0,0]],
        3  => ['L' => [15,1,55,0,0], 'M' => [26,1,44,0,0],  'Q' => [18,2,17,0,0],  'H' => [22,2,13,0,0]],
        4  => ['L' => [20,1,80,0,0], 'M' => [18,2,32,0,0],  'Q' => [26,2,24,0,0],  'H' => [16,4,9,0,0]],
        5  => ['L' => [26,1,108,0,0],'M' => [24,2,43,0,0],  'Q' => [18,2,15,2,16], 'H' => [22,2,11,2,12]],
        6  => ['L' => [18,2,68,0,0], 'M' => [16,4,27,0,0],  'Q' => [24,4,19,0,0],  'H' => [28,4,15,0,0]],
        7  => ['L' => [20,2,78,0,0], 'M' => [18,4,31,0,0],  'Q' => [18,2,14,4,15], 'H' => [26,4,13,1,14]],
        8  => ['L' => [24,2,97,0,0], 'M' => [22,2,38,2,39], 'Q' => [22,4,18,2,19], 'H' => [26,4,14,2,15]],
        9  => ['L' => [30,2,116,0,0],'M' => [22,3,36,2,37], 'Q' => [20,4,16,4,17], 'H' => [24,4,12,4,13]],
        10 => ['L' => [18,2,68,2,69],'M' => [26,4,43,1,44], 'Q' => [24,6,19,2,20], 'H' => [28,6,15,2,16]],
    ];

    /** Alignment-pattern centre coordinates per version (1..10). */
    private const ALIGN = [
        1 => [], 2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30],
        6 => [6,34], 7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
    ];

    /** Format-info EC indicator bits: M=0, L=1, H=2, Q=3. */
    private const FORMAT_BITS = ['M' => 0, 'L' => 1, 'H' => 2, 'Q' => 3];

    private function __construct() {}

    /* =========================================================================
     |  PUBLIC API
     |========================================================================*/

    /**
     * Encode a string as a QR Code. Throws RuntimeException if it does not fit
     * within version 10 at the requested EC level.
     */
    public static function encode(string $data, string $ecLevel = 'M'): self
    {
        self::initGF();
        $ecLevel = strtoupper($ecLevel);
        if (!isset(self::FORMAT_BITS[$ecLevel])) {
            $ecLevel = 'M';
        }

        [$version, $dataCW] = self::chooseVersion($data, $ecLevel);
        $bits    = self::buildDataBits($data, $version, $ecLevel, $dataCW);
        $allCW   = self::buildCodewords($bits, $version, $ecLevel);

        $qr = new self();
        $qr->version = $version;
        $qr->ecLevel = $ecLevel;
        $qr->size    = 21 + ($version - 1) * 4;
        $qr->buildMatrix($allCW);
        return $qr;
    }

    /** The module matrix as 0/1 ints (no quiet zone). */
    public function matrix(): array
    {
        return $this->modules;
    }

    /**
     * Render as a standalone SVG string.
     *
     * @param int    $quiet  quiet-zone width in modules
     * @param int    $scale  pixels per module
     */
    public function svg(int $quiet = 4, int $scale = 6, string $dark = '#000000', string $light = '#ffffff'): string
    {
        $n   = $this->size + 2 * $quiet;
        $dim = $n * $scale;
        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $px = ($x + $quiet) * $scale;
                    $py = ($y + $quiet) * $scale;
                    $path .= "M{$px} {$py}h{$scale}v{$scale}h-{$scale}z";
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" '
            . 'viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="' . $dim . '" height="' . $dim . '" fill="' . $light . '"/>'
            . '<path d="' . $path . '" fill="' . $dark . '"/></svg>';
    }

    /* =========================================================================
     |  GF(256) + REED-SOLOMON
     |========================================================================*/

    private static function initGF(): void
    {
        if (self::$exp !== null) {
            return;
        }
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** Reed-Solomon generator polynomial of the given degree (leading coeff 1). */
    private static function rsGenerator(int $degree): array
    {
        $poly = [1];
        for ($d = 0; $d < $degree; $d++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $i => $coef) {
                $next[$i]     ^= $coef;
                $next[$i + 1] ^= self::gfMul($coef, self::$exp[$d]);
            }
            $poly = $next;
        }
        return $poly;
    }

    /** RS error-correction codewords for a block of data codewords. */
    private static function rsEncode(array $data, int $ecCount): array
    {
        $gen = self::rsGenerator($ecCount);
        $res = array_merge($data, array_fill(0, $ecCount, 0));
        $len = count($data);
        for ($i = 0; $i < $len; $i++) {
            $coef = $res[$i];
            if ($coef !== 0) {
                foreach ($gen as $j => $g) {
                    $res[$i + $j] ^= self::gfMul($g, $coef);
                }
            }
        }
        return array_slice($res, $len, $ecCount);
    }

    /* =========================================================================
     |  DATA ENCODING
     |========================================================================*/

    private static function totalDataCW(int $version, string $ec): int
    {
        [, $g1, $g1d, $g2, $g2d] = self::ECC[$version][$ec];
        return $g1 * $g1d + $g2 * $g2d;
    }

    /** Byte-mode character-count indicator length in bits. */
    private static function charCountBits(int $version): int
    {
        return $version <= 9 ? 8 : 16;
    }

    /** Pick the smallest version (1..10) that fits, returning [version, dataCW]. */
    private static function chooseVersion(string $data, string $ec): array
    {
        $len = strlen($data);
        for ($v = 1; $v <= 10; $v++) {
            $dataCW = self::totalDataCW($v, $ec);
            $needed = 4 + self::charCountBits($v) + 8 * $len; // mode + count + payload
            if ($needed <= $dataCW * 8) {
                return [$v, $dataCW];
            }
        }
        throw new RuntimeException('QR payload too large for version 10 at EC level ' . $ec);
    }

    /** Build the padded data bit array (length dataCW*8). */
    private static function buildDataBits(string $data, int $version, string $ec, int $dataCW): array
    {
        $bits = [];
        $push = static function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };

        $push(0b0100, 4);                              // byte mode
        $push(strlen($data), self::charCountBits($version)); // char count
        foreach (str_split($data) as $ch) {
            $push(ord($ch), 8);
        }

        $capacity = $dataCW * 8;
        // Terminator (up to 4 zero bits).
        $terminator = min(4, $capacity - count($bits));
        for ($i = 0; $i < $terminator; $i++) {
            $bits[] = 0;
        }
        // Pad to a byte boundary.
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        // Pad bytes: alternating 0xEC / 0x11.
        $pad = [0xEC, 0x11];
        $pi  = 0;
        while (count($bits) < $capacity) {
            $push($pad[$pi % 2], 8);
            $pi++;
        }
        return $bits;
    }

    /** Convert data bits into the final interleaved codeword byte array. */
    private static function buildCodewords(array $bits, int $version, string $ec): array
    {
        // Pack bits into data codewords.
        $data = [];
        for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | ($bits[$i + $j] ?? 0);
            }
            $data[] = $byte;
        }

        [$ecPerBlock, $g1, $g1d, $g2, $g2d] = self::ECC[$version][$ec];

        // Split into blocks and compute EC per block.
        $blocks   = [];
        $ecBlocks = [];
        $offset   = 0;
        $specs = array_merge(
            array_fill(0, $g1, $g1d),
            array_fill(0, $g2, $g2d)
        );
        foreach ($specs as $count) {
            $block      = array_slice($data, $offset, $count);
            $offset    += $count;
            $blocks[]   = $block;
            $ecBlocks[] = self::rsEncode($block, $ecPerBlock);
        }

        // Interleave data codewords.
        $result   = [];
        $maxData  = max($g1d, $g2d);
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block)) {
                    $result[] = $block[$i];
                }
            }
        }
        // Interleave EC codewords.
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $result[] = $block[$i];
            }
        }
        return $result;
    }

    /* =========================================================================
     |  MATRIX CONSTRUCTION
     |========================================================================*/

    private array $isFunction;

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x]    = $dark ? 1 : 0;
        $this->isFunction[$y][$x] = true;
    }

    private function buildMatrix(array $codewords): void
    {
        $size = $this->size;
        $this->modules    = array_fill(0, $size, array_fill(0, $size, 0));
        $this->isFunction = array_fill(0, $size, array_fill(0, $size, false));

        $this->drawFunctionPatterns();
        $this->drawCodewords($codewords);

        // Choose the mask with the lowest penalty.
        $bestMask = 0;
        $minPenalty = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->applyMask($m);
            $this->drawFormatBits($m);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $minPenalty = $penalty;
                $bestMask   = $m;
            }
            $this->applyMask($m); // undo (XOR is its own inverse)
        }
        $this->applyMask($bestMask);
        $this->drawFormatBits($bestMask);
    }

    private function drawFunctionPatterns(): void
    {
        $size = $this->size;

        // Timing patterns.
        for ($i = 0; $i < $size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }

        // Finder patterns (with separators) at the three corners.
        $this->drawFinder(3, 3);
        $this->drawFinder($size - 4, 3);
        $this->drawFinder(3, $size - 4);

        // Alignment patterns.
        $pos = self::ALIGN[$this->version];
        $count = count($pos);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)) {
                    continue; // skip finder corners
                }
                $this->drawAlignment($pos[$i], $pos[$j]);
            }
        }

        // Reserve format + version info areas (filled later), plus dark module.
        $this->drawFormatBits(0);            // placeholder reservation
        $this->drawVersionBits();
        $this->set(8, $size - 8, true);      // always-dark module
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x < 0 || $x >= $this->size || $y < 0 || $y >= $this->size) {
                    continue;
                }
                $dist = max(abs($dx), abs($dy));
                $this->set($x, $y, $dist !== 2 && $dist !== 4);
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $size = $this->size;
        // 5 data bits: EC indicator (2) + mask (3), then BCH(15,5).
        $data = (self::FORMAT_BITS[$this->ecLevel] << 3) | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412; // 15 bits

        $bit = static fn(int $i): bool => (($bits >> $i) & 1) !== 0;

        // First copy (around top-left finder).
        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        // Second copy (split across top-right + bottom-left).
        for ($i = 0; $i < 8; $i++) {
            $this->set($size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $size - 15 + $i, $bit($i));
        }
        $this->set(8, $size - 8, true); // dark module
    }

    private function drawVersionBits(): void
    {
        if ($this->version < 7) {
            return;
        }
        $size = $this->size;
        $rem  = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem; // 18 bits

        for ($i = 0; $i < 18; $i++) {
            $b = (($bits >> $i) & 1) !== 0;
            $a = $size - 11 + $i % 3;
            $c = intdiv($i, 3);
            $this->set($a, $c, $b);
            $this->set($c, $a, $b);
        }
    }

    private function drawCodewords(array $codewords): void
    {
        $size = $this->size;
        $bitLen = count($codewords) * 8;
        $i = 0; // bit index

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the vertical timing column
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $bitLen) {
                        $byte = $codewords[$i >> 3];
                        $bit  = ($byte >> (7 - ($i & 7))) & 1;
                        $this->modules[$y][$x] = $bit;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => false,
                };
                if ($invert) {
                    $this->modules[$y][$x] ^= 1;
                }
            }
        }
    }

    /* =========================================================================
     |  MASK PENALTY (classic 4 rules)
     |========================================================================*/

    private function penaltyScore(): int
    {
        $size = $this->size;
        $m = $this->modules;
        $penalty = 0;

        // Rule 1: runs of 5+ in rows and columns.
        for ($y = 0; $y < $size; $y++) {
            $run = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($m[$y][$x] === $m[$y][$x - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) { $penalty += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $penalty += 3 + ($run - 5); }
        }
        for ($x = 0; $x < $size; $x++) {
            $run = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($m[$y][$x] === $m[$y - 1][$x]) {
                    $run++;
                } else {
                    if ($run >= 5) { $penalty += 3 + ($run - 5); }
                    $run = 1;
                }
            }
            if ($run >= 5) { $penalty += 3 + ($run - 5); }
        }

        // Rule 2: 2x2 same-colour blocks.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: finder-like 1:1:3:1:1 patterns with 4 light modules on a side.
        $p1 = [1,0,1,1,1,0,1,0,0,0,0];
        $p2 = [0,0,0,0,1,0,1,1,1,0,1];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x <= $size - 11; $x++) {
                if (self::windowMatches($m[$y], $x, $p1) || self::windowMatches($m[$y], $x, $p2)) {
                    $penalty += 40;
                }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            $col = [];
            for ($y = 0; $y < $size; $y++) {
                $col[] = $m[$y][$x];
            }
            for ($y = 0; $y <= $size - 11; $y++) {
                if (self::windowMatches($col, $y, $p1) || self::windowMatches($col, $y, $p2)) {
                    $penalty += 40;
                }
            }
        }

        // Rule 4: deviation of dark-module proportion from 50%.
        $dark = 0;
        foreach ($m as $row) {
            $dark += array_sum($row);
        }
        $total = $size * $size;
        $ratio = $dark * 100.0 / $total;
        $penalty += (int) (floor(abs($ratio - 50) / 5) * 10);

        return $penalty;
    }

    private static function windowMatches(array $line, int $start, array $pattern): bool
    {
        foreach ($pattern as $k => $want) {
            if (($line[$start + $k] ?? -1) !== $want) {
                return false;
            }
        }
        return true;
    }

    /* =========================================================================
     |  SELF-TEST (structural + GF sanity)
     |========================================================================*/

    /** Returns [] on success, or a list of failure messages. */
    public static function selfTest(): array
    {
        $fail = [];
        self::initGF();

        // GF inverse property.
        for ($a = 1; $a < 256; $a++) {
            if (self::$exp[self::$log[$a]] !== $a) {
                $fail[] = "GF exp/log mismatch at $a";
                break;
            }
        }
        // Known GF product in the 0x11d field: alpha^1 * alpha^7 = alpha^8 = 29.
        if (self::gfMul(2, 128) !== 29) {
            $fail[] = 'GF multiply wrong: mul(2,128) != 29';
        }
        // RS generator of degree 10 has 11 coefficients, leading coeff 1.
        $g = self::rsGenerator(10);
        if (count($g) !== 11 || $g[0] !== 1) {
            $fail[] = 'RS generator degree/leading-coeff wrong';
        }
        // Independent oracle: published degree-10 generator polynomial, as the
        // GF(256) log (alpha exponent) of each coefficient.
        $expectedExp = [0, 251, 67, 46, 61, 118, 70, 64, 94, 32, 45];
        $actualExp = array_map(static fn(int $c): int => self::$log[$c], $g);
        if ($actualExp !== $expectedExp) {
            $fail[] = 'RS generator poly mismatch: ' . implode(',', $actualExp);
        }

        // Structural: encode a URL and validate finder patterns + size.
        $qr = self::encode('https://eduskillindia.org/verify-member?token=' . str_repeat('a', 32), 'M');
        $s  = $qr->size;
        if ($s !== 21 + ($qr->version - 1) * 4) {
            $fail[] = 'Matrix size inconsistent with version';
        }
        // Finder centre (3,3) must be dark, its ring (dist 2) light.
        if ($qr->modules[3][3] !== 1 || $qr->modules[3][1] !== 0) {
            $fail[] = 'Top-left finder pattern malformed';
        }
        // Timing pattern alternation on row 6.
        if ($qr->modules[6][8] !== ($qr->modules[6][9] ^ 1)) {
            $fail[] = 'Timing pattern not alternating';
        }
        // Dark module must be set.
        if ($qr->modules[$s - 8][8] !== 1) {
            $fail[] = 'Dark module not set';
        }
        return $fail;
    }
}
