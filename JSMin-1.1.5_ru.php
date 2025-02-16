<?php
/**
 * jsmin.php - PHP ���������� JSMin � �����������:
 * - ��������� ��������� ����� (ES6)
 * - ���������� ��������� ����� ������ ����� � ���������� ���������
 * - �������� ������������ � ������ ��������
 */
class JSMin {
    // ASCII ���� ����������� ��������
    const ORD_LF            = 10;    // "\n"
    const ORD_SPACE         = 32;    // ������

    // ���� �������� ��� ��������� ��������
    const ACTION_KEEP_A     = 1;     // ��������� ������ A
    const ACTION_DELETE_A   = 2;     // ������� ������ A
    const ACTION_DELETE_A_B = 3;     // ������� A � B

    // ��������� �������
    protected $a           = '';     // ������� ������
    protected $b           = '';     // ��������� ������
    protected $input       = '';     // ������� ������
    protected $inputIndex  = 0;      // ������� ������� � ������
    protected $inputLength = 0;      // ����� ������� ������
    protected $lookAhead   = null;   // ����� ��� ������������ ������
    protected $output      = '';     // ��������� �����������

    // ����� ���������
    protected $inString    = false;  // ������ ������ (' " `)
    protected $inRegex     = false;  // ������ ����������� ���������
    protected $stringChar  = '';     // ��� ������� ������� ������

    /**
     * ����������� ����� ��� ������� �����������
     * @param string $js �������� JavaScript ���
     * @return string ���������������� ���
     */
    public static function minify($js) {
        $jsmin = new JSMin($js);
        return $jsmin->min();
    }

    /**
     * �������������� ������
     * @param string $input ������� JS ���
     */
    public function __construct($input) {
        // ������������ ��������� �����
        $this->input = str_replace("\r\n", "\n", $input);
        $this->inputLength = strlen($this->input);
    }

    /**
     * ��������� �������� � ����������� �� �������
     * @param int $command ���� �� �������� ACTION_*
     */
    protected function action($command) {
        switch($command) {
            case self::ACTION_KEEP_A:
                // �������� \n �� ������ ��� �����/���������
                if ($this->a === "\n" && !$this->inString && !$this->inRegex) {
                    $this->a = ' ';
                }
                $this->output .= $this->a;

            case self::ACTION_DELETE_A:
                $this->a = $this->b;

                // ��������� ������ ������
                if ($this->a === "'" || $this->a === '"' || $this->a === '`') {
                    $this->inString = true;
                    $this->stringChar = $this->a;
                    $this->output .= $this->a;
                    $this->processString();
                }

            case self::ACTION_DELETE_A_B:
                $this->b = $this->next();

                // ��������� ������ ����������� ���������
                if ($this->b === '/' && !$this->inString && (
                    $this->a === '(' || $this->a === ',' || $this->a === '=' ||
                    $this->a === ':' || $this->a === '[' || $this->a === '!' ||
                    $this->a === '&' || $this->a === '|' || $this->a === '?' ||
                    $this->a === '{' || $this->a === '}' || $this->a === ';' ||
                    $this->a === "\n")) {

                    $this->inRegex = true;
                    $this->output .= $this->a . $this->b;
                    $this->processRegex();
                    $this->inRegex = false;
                }
        }
    }

    /**
     * ������������ ���������� ���������� ��������
     */
    protected function processString() {
        for (;;) {
            $this->a = $this->get();

            // ������������� ��������
            if ($this->a === '\\') {
                $this->output .= $this->a;
                $this->a = $this->get();
            }
            // ����� ������
            elseif ($this->a === $this->stringChar) {
                $this->inString = false;
                break;
            }
            // ������������� ������� ������
            elseif (ord($this->a) <= self::ORD_LF) {
                throw new JSMinException('���������� ��������� �������');
            }

            $this->output .= $this->a;
        }
    }

    /**
     * ������������ ���������� ����������� ���������
     */
    protected function processRegex() {
        for (;;) {
            $this->a = $this->get();

            // ��������� ���������� ������� [...]
            if ($this->a === '[') {
                do {
                    $this->output .= $this->a;
                    $this->a = $this->get();
                } while ($this->a !== ']');
            }
            // ����� ����������� ���������
            elseif ($this->a === '/') {
                break;
            }
            // ������������� � ����������
            elseif ($this->a === '\\') {
                $this->output .= $this->a;
                $this->a = $this->get();
            }
            // ������������ ������
            elseif (ord($this->a) <= self::ORD_LF) {
                throw new JSMinException('���������� ���������� ���������');
            }

            $this->output .= $this->a;
        }
        $this->b = $this->next();
    }

    /**
     * �������� ��������� ������ � ���������� ����������� ��������
     * @return string|null
     */
    protected function get() {
        $c = $this->lookAhead;
        $this->lookAhead = null;

        // ������ �� ������� ������
        if ($c === null) {
            if ($this->inputIndex < $this->inputLength) {
                $c = substr($this->input, $this->inputIndex, 1);
                $this->inputIndex += 1;
            } else {
                $c = null;
            }
        }

        // ������������ ��������� �����
        if ($c === "\r") return "\n";

        // ������ ����������� �������� �� �������
        return ($c === null || $c === "\n" || ord($c) >= self::ORD_SPACE)
            ? $c
            : ' ';
    }

    /**
     * ���������, �������� �� ������ ������ ��������������
     * @param string $c ����������� ������
     * @return bool
     */
    protected function isAlphaNum($c) {
        return ord($c) > 126 || $c === '\\' || preg_match('/^[\w\$]$/', $c) === 1;
    }

    /**
     * ������� ����� �����������
     * @return string ���������������� ���
     */
    protected function min() {
        // ������� BOM (UTF-8 �������)
        if (0 == strncmp($this->peek(), "\xef", 1)) {
            $this->get(); $this->get(); $this->get();
        }

        // ������������� ���������� ���������
        $this->a = "\n";
        $this->action(self::ACTION_DELETE_A_B);

        // �������� ���� ���������
        while ($this->a !== null) {
            // ��������� ��������� �����
            if ($this->a === "\n") {
                if ($this->inString || $this->inRegex) {
                    // ��������� �������� ������ �����/���������
                    $this->output .= $this->a;
                    $this->a = $this->get();
                    continue;
                } else {
                    // �������� �� ������ ��� ����. ����������
                    $this->a = ' ';
                }
            }

            // ������ ��������� ��������
            switch ($this->a) {
                case ' ':
                    $this->isAlphaNum($this->b)
                        ? $this->action(self::ACTION_KEEP_A)
                        : $this->action(self::ACTION_DELETE_A);
                    break;

                case "\n":
                    break; // ���������� �����

                default:
                    switch ($this->b) {
                        case ' ':
                            $this->isAlphaNum($this->a)
                                ? $this->action(self::ACTION_KEEP_A)
                                : $this->action(self::ACTION_DELETE_A_B);
                            break;

                        case "\n":
                            ($this->inString || $this->inRegex)
                                ? $this->action(self::ACTION_KEEP_A)
                                : $this->action(self::ACTION_DELETE_A_B);
                            break;

                        default:
                            $this->action(self::ACTION_KEEP_A);
                            break;
                    }
            }
        }

        return $this->output;
    }

    /**
     * �������� ��������� ������, ��������� �����������
     * @return string
     * @throws JSMinException ��� ���������� ������������
     */
    protected function next() {
        $c = $this->get();

        // ��������� ������������
        if ($c === '/') {
            switch($this->peek()) {
                case '/':
                    // ������������ �����������
                    while (ord($c = $this->get()) > self::ORD_LF);
                    return $c;

                case '*':
                    // ������������� �����������
                    $this->get(); // ���������� *
                    while (true) {
                        switch($this->get()) {
                            case '*':
                                if ($this->peek() === '/') {
                                    $this->get();
                                    return ' ';
                                }
                                break;
                            case null:
                                throw new JSMinException('���������� �����������');
                        }
                    }

                default:
                    return $c;
            }
        }

        return $c;
    }

    /**
     * ������������� ��������� ������ ��� ����������� ���������
     * @return string|null
     */
    protected function peek() {
        $this->lookAhead = $this->get();
        return $this->lookAhead;
    }
}

class JSMinException extends Exception {}
?>
