<?php
/**
 * jsmin.php - PHP реализация JSMin с доработками:
 * - Поддержка шаблонных строк (ES6)
 * - Сохранение переводов строк внутри строк и регулярных выражений
 * - Удаление комментариев и лишних пробелов
 */
class JSMin {
    // ASCII коды управляющих символов
    const ORD_LF            = 10;    // "\n"
    const ORD_SPACE         = 32;    // пробел

    // Типы действий при обработке символов
    const ACTION_KEEP_A     = 1;     // Сохранить символ A
    const ACTION_DELETE_A   = 2;     // Удалить символ A
    const ACTION_DELETE_A_B = 3;     // Удалить A и B

    // Состояние парсера
    protected $a           = '';     // Текущий символ
    protected $b           = '';     // Следующий символ
    protected $input       = '';     // Входная строка
    protected $inputIndex  = 0;      // Текущая позиция в строке
    protected $inputLength = 0;      // Длина входной строки
    protected $lookAhead   = null;   // Буфер для опережающего чтения
    protected $output      = '';     // Результат минификации

    // Флаги контекста
    protected $inString    = false;  // Внутри строки (' " `)
    protected $inRegex     = false;  // Внутри регулярного выражения
    protected $stringChar  = '';     // Тип кавычек текущей строки

    /**
     * Статический метод для быстрой минификации
     * @param string $js Исходный JavaScript код
     * @return string Минифицированный код
     */
    public static function minify($js) {
        $jsmin = new JSMin($js);
        return $jsmin->min();
    }

    /**
     * Инициализирует парсер
     * @param string $input Входной JS код
     */
    public function __construct($input) {
        // Нормализация переводов строк
        $this->input = str_replace("\r\n", "\n", $input);
        $this->inputLength = strlen($this->input);
    }

    /**
     * Выполняет действие в зависимости от команды
     * @param int $command Одна из констант ACTION_*
     */
    protected function action($command) {
        switch($command) {
            case self::ACTION_KEEP_A:
                // Заменяем \n на пробел вне строк/регулярок
                if ($this->a === "\n" && !$this->inString && !$this->inRegex) {
                    $this->a = ' ';
                }
                $this->output .= $this->a;

            case self::ACTION_DELETE_A:
                $this->a = $this->b;

                // Обработка начала строки
                if ($this->a === "'" || $this->a === '"' || $this->a === '`') {
                    $this->inString = true;
                    $this->stringChar = $this->a;
                    $this->output .= $this->a;
                    $this->processString();
                }

            case self::ACTION_DELETE_A_B:
                $this->b = $this->next();

                // Обработка начала регулярного выражения
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
     * Обрабатывает содержимое строкового литерала
     */
    protected function processString() {
        for (;;) {
            $this->a = $this->get();

            // Экранирование символов
            if ($this->a === '\\') {
                $this->output .= $this->a;
                $this->a = $this->get();
            }
            // Конец строки
            elseif ($this->a === $this->stringChar) {
                $this->inString = false;
                break;
            }
            // Неразрешённый перевод строки
            elseif (ord($this->a) <= self::ORD_LF) {
                throw new JSMinException('Незакрытый строковый литерал');
            }

            $this->output .= $this->a;
        }
    }

    /**
     * Обрабатывает содержимое регулярного выражения
     */
    protected function processRegex() {
        for (;;) {
            $this->a = $this->get();

            // Обработка символьных классов [...]
            if ($this->a === '[') {
                do {
                    $this->output .= $this->a;
                    $this->a = $this->get();
                } while ($this->a !== ']');
            }
            // Конец регулярного выражения
            elseif ($this->a === '/') {
                break;
            }
            // Экранирование в регулярках
            elseif ($this->a === '\\') {
                $this->output .= $this->a;
                $this->a = $this->get();
            }
            // Недопустимый символ
            elseif (ord($this->a) <= self::ORD_LF) {
                throw new JSMinException('Незакрытое регулярное выражение');
            }

            $this->output .= $this->a;
        }
        $this->b = $this->next();
    }

    /**
     * Получает следующий символ с обработкой управляющих символов
     * @return string|null
     */
    protected function get() {
        $c = $this->lookAhead;
        $this->lookAhead = null;

        // Чтение из входной строки
        if ($c === null) {
            if ($this->inputIndex < $this->inputLength) {
                $c = substr($this->input, $this->inputIndex, 1);
                $this->inputIndex += 1;
            } else {
                $c = null;
            }
        }

        // Нормализация переводов строк
        if ($c === "\r") return "\n";

        // Замена управляющих символов на пробелы
        return ($c === null || $c === "\n" || ord($c) >= self::ORD_SPACE)
            ? $c
            : ' ';
    }

    /**
     * Проверяет, является ли символ частью идентификатора
     * @param string $c Проверяемый символ
     * @return bool
     */
    protected function isAlphaNum($c) {
        return ord($c) > 126 || $c === '\\' || preg_match('/^[\w\$]$/', $c) === 1;
    }

    /**
     * Главный метод минификации
     * @return string Минифицированный код
     */
    protected function min() {
        // Пропуск BOM (UTF-8 маркера)
        if (0 == strncmp($this->peek(), "\xef", 1)) {
            $this->get(); $this->get(); $this->get();
        }

        // Инициализация начального состояния
        $this->a = "\n";
        $this->action(self::ACTION_DELETE_A_B);

        // Основной цикл обработки
        while ($this->a !== null) {
            // Обработка переводов строк
            if ($this->a === "\n") {
                if ($this->inString || $this->inRegex) {
                    // Сохраняем переводы внутри строк/регулярок
                    $this->output .= $this->a;
                    $this->a = $this->get();
                    continue;
                } else {
                    // Заменяем на пробел вне спец. контекстов
                    $this->a = ' ';
                }
            }

            // Логика обработки символов
            switch ($this->a) {
                case ' ':
                    $this->isAlphaNum($this->b)
                        ? $this->action(self::ACTION_KEEP_A)
                        : $this->action(self::ACTION_DELETE_A);
                    break;

                case "\n":
                    break; // Обработано ранее

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
     * Получает следующий символ, пропуская комментарии
     * @return string
     * @throws JSMinException При незакрытых комментариях
     */
    protected function next() {
        $c = $this->get();

        // Обработка комментариев
        if ($c === '/') {
            switch($this->peek()) {
                case '/':
                    // Однострочный комментарий
                    while (ord($c = $this->get()) > self::ORD_LF);
                    return $c;

                case '*':
                    // Многострочный комментарий
                    $this->get(); // Пропускаем *
                    while (true) {
                        switch($this->get()) {
                            case '*':
                                if ($this->peek() === '/') {
                                    $this->get();
                                    return ' ';
                                }
                                break;
                            case null:
                                throw new JSMinException('Незакрытый комментарий');
                        }
                    }

                default:
                    return $c;
            }
        }

        return $c;
    }

    /**
     * Просматривает следующий символ без перемещения указателя
     * @return string|null
     */
    protected function peek() {
        $this->lookAhead = $this->get();
        return $this->lookAhead;
    }
}

class JSMinException extends Exception {}
?>
