<?php
/**
 * Exam content — one certification exam per language category, unlocked
 * after finishing that category's lessons. Same shape philosophy as
 * data/lessons.php: plain PHP arrays, no database, easy to extend.
 *
 * Each question: 'q' (question text), 'options' (4 choices),
 * 'correct' (0-based index of the right option).
 * 'passScore' is the minimum percentage to earn a certificate.
 *
 * Adding a question: append to the category's 'questions' array — no
 * other file needs to change. Adding an exam for a brand-new language:
 * add a top-level key here matching the category key in lessons.php.
 */

function inkwell_exams() {
  static $EXAMS = [
    'html' => [
      'title' => 'HTML Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'What does the <!DOCTYPE html> declaration do?', 'options' => ['Loads a CSS file', 'Tells the browser to use standard HTML rules', 'Starts a comment', 'Defines the page title'], 'correct' => 1],
        ['q' => 'Which attribute specifies where a link points to?', 'options' => ['src', 'href', 'link', 'target'], 'correct' => 1],
        ['q' => 'Which tag creates an unordered (bulleted) list?', 'options' => ['<ol>', '<list>', '<ul>', '<li>'], 'correct' => 2],
        ['q' => 'Which tag defines a row inside a table?', 'options' => ['<td>', '<tr>', '<th>', '<row>'], 'correct' => 1],
        ['q' => 'Which attribute provides alternate text for an image?', 'options' => ['title', 'alt', 'caption', 'desc'], 'correct' => 1],
      ],
    ],
    'css' => [
      'title' => 'CSS Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'In the CSS box model, which layer sits directly outside the border?', 'options' => ['Padding', 'Content', 'Margin', 'Outline'], 'correct' => 2],
        ['q' => 'Which selector targets all elements with class="card"?', 'options' => ['#card', '.card', '*card', ':card'], 'correct' => 1],
        ['q' => 'Which property turns a container into a flex container?', 'options' => ['display: flex', 'position: flex', 'layout: flex', 'flex: true'], 'correct' => 0],
        ['q' => 'Which property controls the main-axis direction of flex items?', 'options' => ['align-items', 'flex-wrap', 'flex-direction', 'justify-self'], 'correct' => 2],
        ['q' => 'Which property animates a property change smoothly over time?', 'options' => ['animation-name', 'transition', 'transform', 'ease'], 'correct' => 1],
      ],
    ],
    'js' => [
      'title' => 'JavaScript Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'Which keyword declares a block-scoped variable?', 'options' => ['var', 'let', 'static', 'define'], 'correct' => 1],
        ['q' => 'Which method prints a message to the browser console?', 'options' => ['print()', 'console.log()', 'log.console()', 'echo()'], 'correct' => 1],
        ['q' => 'Which method selects the first matching element by CSS selector?', 'options' => ['document.querySelector()', 'document.getStyle()', 'document.find()', 'document.select()'], 'correct' => 0],
        ['q' => 'Which event fires when a button is clicked?', 'options' => ['change', 'submit', 'click', 'press'], 'correct' => 2],
        ['q' => 'Which keyword defines a reusable block of code?', 'options' => ['function', 'method', 'block', 'routine'], 'correct' => 0],
      ],
    ],
    'php' => [
      'title' => 'PHP Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'Which symbol prefixes a variable name in PHP?', 'options' => ['@', '$', '#', '&'], 'correct' => 1],
        ['q' => 'Which statement outputs text in PHP?', 'options' => ['print_r only', 'echo', 'write', 'output'], 'correct' => 1],
        ['q' => 'Which superglobal array holds data submitted via a POST form?', 'options' => ['$_GET', '$_POST', '$_FORM', '$_REQUEST_POST'], 'correct' => 1],
        ['q' => 'Which built-in PHP extension is commonly used to talk to a MySQL database?', 'options' => ['PDO', 'PHPMailer', 'Composer', 'cURL'], 'correct' => 0],
        ['q' => 'What file extension do PHP source files typically use?', 'options' => ['.phtml only', '.php', '.phc', '.pph'], 'correct' => 1],
      ],
    ],
    'c' => [
      'title' => 'C Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'Which function prints formatted text to the console in C?', 'options' => ['echo()', 'print()', 'printf()', 'cout'], 'correct' => 2],
        ['q' => 'Which header must be included to use printf?', 'options' => ['<stdlib.h>', '<stdio.h>', '<string.h>', '<stdint.h>'], 'correct' => 1],
        ['q' => 'Which loop is best suited for repeating a known, fixed number of times?', 'options' => ['while', 'do-while', 'for', 'goto'], 'correct' => 2],
        ['q' => 'Which keyword declares a whole-number variable?', 'options' => ['int', 'num', 'integer', 'whole'], 'correct' => 0],
        ['q' => 'Every C program needs which function as its entry point?', 'options' => ['start()', 'main()', 'run()', 'init()'], 'correct' => 1],
      ],
    ],
    'cpp' => [
      'title' => 'C++ Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'Which keyword defines a class in C++?', 'options' => ['struct only', 'class', 'object', 'type'], 'correct' => 1],
        ['q' => 'Which Standard Library container is a resizable array?', 'options' => ['array', 'vector', 'list', 'set'], 'correct' => 1],
        ['q' => 'Which operator sends output to the console with cout?', 'options' => ['>>', '<<', '::', '->'], 'correct' => 1],
        ['q' => 'Which keyword allocates memory for a new object on the heap?', 'options' => ['alloc', 'new', 'create', 'malloc_obj'], 'correct' => 1],
        ['q' => 'Which header provides std::vector?', 'options' => ['<vector>', '<array>', '<list>', '<memory>'], 'correct' => 0],
      ],
    ],
    'java' => [
      'title' => 'Java Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'Which method is the entry point of a Java application?', 'options' => ['start()', 'main()', 'run()', 'init()'], 'correct' => 1],
        ['q' => 'Which keyword declares a class in Java?', 'options' => ['class', 'struct', 'object', 'define'], 'correct' => 0],
        ['q' => 'Which loop checks its condition before running and may execute zero times?', 'options' => ['do-while', 'for', 'while', 'repeat'], 'correct' => 2],
        ['q' => 'Which keyword declares a constant in Java?', 'options' => ['const', 'final', 'static', 'fixed'], 'correct' => 1],
        ['q' => 'Which type holds a true/false value in Java?', 'options' => ['bit', 'flag', 'boolean', 'bool'], 'correct' => 2],
      ],
    ],
    'python' => [
      'title' => 'Python Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'Which function prints text to the console in Python?', 'options' => ['echo()', 'print()', 'write()', 'log()'], 'correct' => 1],
        ['q' => 'Which keyword defines a function in Python?', 'options' => ['func', 'def', 'function', 'lambda only'], 'correct' => 1],
        ['q' => 'Which data type is an ordered, changeable collection of items?', 'options' => ['tuple', 'set', 'list', 'dict'], 'correct' => 2],
        ['q' => 'Which loop iterates directly over the items of a sequence?', 'options' => ['for item in sequence', 'while sequence', 'loop sequence', 'foreach sequence'], 'correct' => 0],
        ['q' => 'Which symbol starts a single-line comment in Python?', 'options' => ['//', '#', '--', '/*'], 'correct' => 1],
      ],
    ],
    'csharp' => [
      'title' => 'C# Certification Exam',
      'passScore' => 70,
      'questions' => [
        ['q' => 'Which method is the entry point of a C# console app?', 'options' => ['Start()', 'Main()', 'Run()', 'Init()'], 'correct' => 1],
        ['q' => "Which keyword declares a variable whose type is inferred by the compiler?", 'options' => ['auto', 'var', 'infer', 'let'], 'correct' => 1],
        ['q' => 'Which loop repeats a block while a condition remains true?', 'options' => ['for', 'while', 'switch', 'foreach'], 'correct' => 1],
        ['q' => 'Which keyword defines a class in C#?', 'options' => ['class', 'struct only', 'object', 'type'], 'correct' => 0],
        ['q' => 'Which namespace provides Console.WriteLine?', 'options' => ['System.IO', 'System', 'System.Text', 'Microsoft.Console'], 'correct' => 1],
      ],
    ],
  ];
  return $EXAMS;
}

function inkwell_exam($catKey) {
  $exams = inkwell_exams();
  return $exams[$catKey] ?? null;
}
