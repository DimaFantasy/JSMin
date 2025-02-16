# JSMin PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A PHP implementation of Douglas Crockford's JSMin with enhanced support for modern JavaScript features. Minifies JavaScript code while preserving formatting inside template literals and regular expressions.

## Features

- 🛡️ **Template Literal Support**  
  Preserves line breaks and formatting in template strings (`` `...` ``).

- 🔍 **Regex Preservation**  
  Keeps regular expressions intact, including multi-line patterns.

- 🗑️ **Comment Removal**  
  Removes both single-line (`//`) and multi-line (`/* */`) comments.

- ⚡ **Whitespace Optimization**  
  Converts unnecessary whitespace to single spaces (except in strings/regex).

- 🚀 **Lightweight & Fast**  
  Zero dependencies – pure PHP implementation.

## Usage

### Available Versions
- `JSMin-1.1.5_en.php` – with English comments
- `JSMin-1.1.5_ru.php` – with Russian comments

```php
use YourNamespace\JSMin;

$js = <<<JS
// This is a comment
const greeting = `Hello,
World!`;
console.log(/multi-line-regex/);
JS;

echo JSMin::minify($js);
```

### Output:

```javascript
const greeting=`Hello,\nWorld!`;console.log(/multi-line-regex/);
```

## Why Choose This?

✅ **ES6+ Support** - Handles modern JavaScript features better than the original JSMin  
🧩 **Context-Aware** - Smart handling of strings and regex literals  
🔧 **Reliable** - Properly handles edge cases like:

- Escape sequences (`\n`, `\"`, `\\`)
- Nested comments
- UTF-8 BOM markers
