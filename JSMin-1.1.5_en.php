<?php
/**
 * jsmin.php - PHP implementation of Douglas Crockford's JSMin.
 * Modified version with:
 * - Template literal support
 * - Preserved line breaks in strings/regex
 * - Comment removal and whitespace optimization
 */
class JSMin {
    // ASCII control codes
    const ORD_LF            = 10;    // Line feed ("\n")
    const ORD_SPACE         = 32;    // Space character
    
    // Parser action types
    const ACTION_KEEP_A     = 1;     // Keep current character
    const ACTION_DELETE_A   = 2;     // Delete current character
    const ACTION_DELETE_A_B = 3;     // Delete both characters

    // Parser state
    protected $a           = '';     // Current character
    protected $b           = '';     // Next character
    protected $input       = '';     // Input JS code
    protected $inputIndex  = 0;      // Current input position
    protected $inputLength = 0;      // Input string length
    protected $lookAhead   = null;   // Lookahead buffer
    protected $output      = '';     // Minified output
    
    // Context flags
    protected $inString    = false;  // Inside string literal
    protected $inRegex     = false;  // Inside regex literal
    protected $stringChar  = '';     // String delimiter type

    /**
     * Static entry point for minification
     * @param string $js JavaScript source code
     * @return string Minified code
     */
    public static function minify($js) {
        $jsmin = new JSMin($js);
        return $jsmin->min();
    }

    /**
     * Initialize parser state
     * @param string $input JS source to minify
     */
    public function __construct($input) {
        // Normalize line endings
        $this->input = str_replace("\r\n", "\n", $input);
        $this->inputLength = strlen($this->input);
    }

    /**
     * Execute parsing action
     * @param int $command One of ACTION_* constants
     */
    protected function action($command) {
        switch($command) {
            case self::ACTION_KEEP_A:
                // Convert newlines to spaces outside special contexts
                if ($this->a === "\n" && !$this->inString && !$this->inRegex) {
                    $this->a = ' ';
                }
                $this->output .= $this->a;

            case self::ACTION_DELETE_A:
                $this->a = $this->b;

                // Handle string literal start
                if ($this->a === "'" || $this->a === '"' || $this->a === '`') {
                    $this->inString = true;
                    $this->stringChar = $this->a;
                    $this->output .= $this->a;
                    $this->processString();
                }

            case self::ACTION_DELETE_A_B:
                $this->b = $this->next();

                // Detect regex literal start
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
     * Process string literal contents
     */
    protected function processString() {
        for (;;) {
            $this->a = $this->get();

            // Handle escape sequences
            if ($this->a === '\\') {
                $this->output .= $this->a;
                $this->a = $this->get();
            } 
            // End of string
            elseif ($this->a === $this->stringChar) {
                $this->inString = false;
                break;
            } 
            // Invalid line break
            elseif (ord($this->a) <= self::ORD_LF) {
                throw new JSMinException('Unterminated string literal');
            }

            $this->output .= $this->a;
        }
    }

    /**
     * Process regex literal contents
     */
    protected function processRegex() {
        for (;;) {
            $this->a = $this->get();

            // Handle character classes
            if ($this->a === '[') {
                do {
                    $this->output .= $this->a;
                    $this->a = $this->get();
                } while ($this->a !== ']');
            } 
            // End of regex
            elseif ($this->a === '/') {
                break;
            } 
            // Escape sequences
            elseif ($this->a === '\\') {
                $this->output .= $this->a;
                $this->a = $this->get();
            } 
            // Invalid character
            elseif (ord($this->a) <= self::ORD_LF) {
                throw new JSMinException('Unterminated regex literal');
            }

            $this->output .= $this->a;
        }
        $this->b = $this->next();
    }

    /**
     * Get next character with control character handling
     * @return string|null
     */
    protected function get() {
        $c = $this->lookAhead;
        $this->lookAhead = null;

        if ($c === null) {
            if ($this->inputIndex < $this->inputLength) {
                $c = substr($this->input, $this->inputIndex, 1);
                $this->inputIndex += 1;
            } else {
                $c = null;
            }
        }

        // Normalize line endings
        if ($c === "\r") return "\n";
        
        // Convert control characters to spaces
        return ($c === null || $c === "\n" || ord($c) >= self::ORD_SPACE) 
            ? $c 
            : ' ';
    }

    /**
     * Check if character is alphanumeric
     * @param string $c Character to check
     * @return bool
     */
    protected function isAlphaNum($c) {
        return ord($c) > 126 || $c === '\\' || preg_match('/^[\w\$]$/', $c) === 1;
    }

    /**
     * Main minification process
     * @return string Minified output
     */
    protected function min() {
        // Skip UTF-8 BOM if present
        if (0 == strncmp($this->peek(), "\xef", 1)) {
            $this->get(); $this->get(); $this->get();
        }

        // Initialize parser state
        $this->a = "\n";
        $this->action(self::ACTION_DELETE_A_B);

        // Main processing loop
        while ($this->a !== null) {
            // Handle newlines based on context
            if ($this->a === "\n") {
                if ($this->inString || $this->inRegex) {
                    // Preserve newlines in strings/regex
                    $this->output .= $this->a;
                    $this->a = $this->get();
                    continue;
                } else {
                    // Convert to space outside special contexts
                    $this->a = ' ';
                }
            }

            // Character processing logic
            switch ($this->a) {
                case ' ':
                    $this->isAlphaNum($this->b) 
                        ? $this->action(self::ACTION_KEEP_A)
                        : $this->action(self::ACTION_DELETE_A);
                    break;

                case "\n":
                    break; // Handled above

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
     * Get next character with comment skipping
     * @return string
     * @throws JSMinException For unterminated comments
     */
    protected function next() {
        $c = $this->get();

        // Handle comments
        if ($c === '/') {
            switch($this->peek()) {
                case '/':
                    // Skip single-line comments
                    while (ord($c = $this->get()) > self::ORD_LF);
                    return $c;

                case '*':
                    // Skip multi-line comments
                    $this->get(); // Skip '*'
                    while (true) {
                        switch($this->get()) {
                            case '*':
                                if ($this->peek() === '/') {
                                    $this->get();
                                    return ' ';
                                }
                                break;
                            case null:
                                throw new JSMinException('Unterminated comment');
                        }
                    }

                default:
                    return $c;
            }
        }

        return $c;
    }

    /**
     * Peek next character without advancing
     * @return string|null
     */
    protected function peek() {
        $this->lookAhead = $this->get();
        return $this->lookAhead;
    }
}

class JSMinException extends Exception {}
