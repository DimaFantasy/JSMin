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
  Zero dependencies - pure PHP implementation.

## Installation

Via Composer:

```bash
composer require yourname/jsmin
