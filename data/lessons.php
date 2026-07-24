<?php
/**
 * Inkwell — lesson content store
 * Each category has an ordered list of lessons.
 * Each lesson has: slug, title, summary, body (teaching text, simple markup-safe HTML),
 * and starter code for the three editor panes (html/css/js).
 * Swap this file for a MySQL-backed model() later without touching any page templates —
 * every page only calls the functions at the bottom.
 */

$INKWELL_LESSONS = [

  'html' => [
    'label' => 'HTML',
    'course' => 'BSIT',
    'color' => '#c9622b',
    'tagline' => 'Structure the page',
    'runnable' => true,
    'lessons' => [
      'intro' => [
        'title' => 'Your First Element',
        'summary' => 'Tags, elements, and the skeleton every page shares.',
        'body' => "<p>An HTML element is a start tag, some content, and an end tag. Browsers read the tags and decide how to lay everything out — nothing renders until you tell it what it is.</p><p>Every page needs the same three landmarks: <code>&lt;!DOCTYPE html&gt;</code> so the browser knows the rules, an <code>&lt;html&gt;</code> root, and a <code>&lt;body&gt;</code> for anything visible.</p><p>Change the heading and paragraph on the right, then press <strong>Run</strong> to see it update.</p>",
        'html' => "<!DOCTYPE html>\n<html>\n  <body>\n    <h1>Hello, Inkwell</h1>\n    <p>Edit this paragraph, then press Run.</p>\n  </body>\n</html>",
        'css' => "body {\n  font-family: sans-serif;\n  padding: 24px;\n}",
        'js' => ""
      ],
      'attributes' => [
        'title' => 'Attributes & Links',
        'summary' => 'Give elements extra instructions — hrefs, sources, and ids.',
        'body' => "<p>Attributes live inside the opening tag and add information the browser needs: where a link goes, where an image lives, which element a style targets.</p><p>An attribute is always <code>name=\"value\"</code>. A link needs <code>href</code>; an image needs <code>src</code>.</p><p>Try adding a second link, or point the image at a different URL.</p>",
        'html' => "<a href=\"https://example.com\">Visit example.com</a>\n<p>Attributes go inside the opening tag.</p>\n<img src=\"https://placehold.co/200x100\" alt=\"placeholder\">",
        'css' => "body { font-family: sans-serif; padding: 24px; }\na { color: #c9622b; }",
        'js' => ""
      ],
      'lists-tables' => [
        'title' => 'Lists & Tables',
        'summary' => 'Group items and line up data in rows and columns.',
        'body' => "<p><code>&lt;ul&gt;</code> makes an unordered list, <code>&lt;ol&gt;</code> an ordered one, and each item is an <code>&lt;li&gt;</code>. Tables use <code>&lt;table&gt;</code>, <code>&lt;tr&gt;</code> for rows, and <code>&lt;td&gt;</code> for cells.</p><p>Add a third row to the table on the right.</p>",
        'html' => "<ul>\n  <li>Learn HTML</li>\n  <li>Learn CSS</li>\n</ul>\n\n<table border=\"1\">\n  <tr><th>Language</th><th>Job</th></tr>\n  <tr><td>HTML</td><td>Structure</td></tr>\n</table>",
        'css' => "body { font-family: sans-serif; padding: 24px; }\ntable { border-collapse: collapse; margin-top: 12px; }\ntd, th { padding: 6px 12px; }",
        'js' => ""
      ],
    ],
  ],

  'css' => [
    'label' => 'CSS',
    'course' => 'BSIT',
    'color' => '#2d5c4c',
    'tagline' => 'Style what you built',
    'runnable' => true,
    'lessons' => [
      'selectors' => [
        'title' => 'Selectors & the Box Model',
        'summary' => 'Target elements, then control their padding, border, and margin.',
        'body' => "<p>A selector points at elements: a tag name (<code>p</code>), a class (<code>.card</code>), or an id (<code>#header</code>). Every element is a box with <strong>content → padding → border → margin</strong>, working outward.</p><p>Try raising the padding or adding a <code>border-radius</code> to the card.</p>",
        'html' => "<div class=\"card\">\n  <h2>Box Model</h2>\n  <p>Padding sits inside the border. Margin sits outside it.</p>\n</div>",
        'css' => ".card {\n  background: #fdf6ec;\n  border: 2px solid #2d5c4c;\n  padding: 20px;\n  margin: 20px;\n  border-radius: 4px;\n  font-family: sans-serif;\n}",
        'js' => ""
      ],
      'flexbox' => [
        'title' => 'Flexbox Layout',
        'summary' => 'Line elements up in a row or column without floats or hacks.',
        'body' => "<p>Set <code>display: flex</code> on a parent and its children line up along a row by default. <code>justify-content</code> spaces them out; <code>align-items</code> lines them up vertically; <code>gap</code> adds space between them.</p><p>Change <code>justify-content</code> to <code>center</code> or <code>space-between</code> and watch the boxes move.</p>",
        'html' => "<div class=\"row\">\n  <div class=\"box\">1</div>\n  <div class=\"box\">2</div>\n  <div class=\"box\">3</div>\n</div>",
        'css' => ".row {\n  display: flex;\n  gap: 12px;\n  justify-content: flex-start;\n  padding: 24px;\n}\n.box {\n  background: #2d5c4c;\n  color: white;\n  width: 60px;\n  height: 60px;\n  display: flex;\n  align-items: center;\n  justify-content: center;\n  font-family: sans-serif;\n  border-radius: 4px;\n}",
        'js' => ""
      ],
      'transitions' => [
        'title' => 'Transitions',
        'summary' => 'Animate a property change instead of snapping to it.',
        'body' => "<p><code>transition</code> tells the browser to animate a property change over time instead of jumping straight to it. Pair it with a <code>:hover</code> state.</p><p>Hover the button on the right, then try changing the transition duration.</p>",
        'html' => "<button class=\"grow\">Hover me</button>",
        'css' => "button.grow {\n  padding: 12px 20px;\n  font-family: sans-serif;\n  background: #2d5c4c;\n  color: white;\n  border: none;\n  border-radius: 4px;\n  transition: transform 0.25s ease;\n}\nbutton.grow:hover {\n  transform: scale(1.1);\n}",
        'js' => ""
      ],
    ],
  ],

  'js' => [
    'label' => 'JavaScript',
    'course' => 'BSIT',
    'color' => '#3b5fe0',
    'tagline' => 'Make it interactive',
    'runnable' => true,
    'lessons' => [
      'variables' => [
        'title' => 'Variables & Console',
        'summary' => 'Store values, then log them to see what your code is doing.',
        'body' => "<p><code>let</code> declares a variable that can change; <code>const</code> declares one that can't be reassigned. <code>console.log()</code> prints a value — open the Console tab below the preview to see it.</p><p>Change the values and press Run.</p>",
        'html' => "<h2>Open the Console tab to see output</h2>",
        'css' => "body { font-family: sans-serif; padding: 24px; }",
        'js' => "let name = \"learner\";\nconst greeting = \"Hello, \" + name + \"!\";\nconsole.log(greeting);"
      ],
      'dom' => [
        'title' => 'Touching the DOM',
        'summary' => 'Select an element and change what it shows, live.',
        'body' => "<p><code>document.getElementById()</code> grabs an element by its <code>id</code>. Once you have it, you can read or change its <code>.textContent</code>, styles, or attributes.</p><p>Click the button on the right — its script updates the paragraph above it.</p>",
        'html' => "<p id=\"msg\">Nothing clicked yet.</p>\n<button id=\"btn\">Click me</button>",
        'css' => "body { font-family: sans-serif; padding: 24px; }\nbutton { padding: 8px 16px; }",
        'js' => "document.getElementById(\"btn\").addEventListener(\"click\", function () {\n  document.getElementById(\"msg\").textContent = \"Button was clicked!\";\n});"
      ],
      'events' => [
        'title' => 'Events & Functions',
        'summary' => 'Respond to what the user does.',
        'body' => "<p>An event listener waits for something to happen — a click, a keypress, a page load — and runs a function when it does. Functions let you name a block of logic and reuse it.</p><p>Try typing into the input; the count updates as you type.</p>",
        'html' => "<input id=\"box\" placeholder=\"Type here\">\n<p id=\"count\">Characters: 0</p>",
        'css' => "body { font-family: sans-serif; padding: 24px; }",
        'js' => "function updateCount() {\n  const value = document.getElementById(\"box\").value;\n  document.getElementById(\"count\").textContent = \"Characters: \" + value.length;\n}\ndocument.getElementById(\"box\").addEventListener(\"input\", updateCount);"
      ],
    ],
  ],

  'php' => [
    'label' => 'PHP',
    'course' => 'BSIT',
    'color' => '#8a3ffc',
    'tagline' => 'Run it on the server',
    'runnable' => false,
    'monacoLang' => 'php',
    'filename' => 'index.php',
    'lessons' => [
      'basics' => [
        'title' => 'Echo & Variables',
        'summary' => 'PHP runs before the page reaches the browser.',
        'body' => "<p>PHP code lives between <code>&lt;?php</code> and <code>?&gt;</code> and runs on the server, before HTML is sent out. <code>echo</code> writes text into the page. Variables start with <code>\$</code>.</p><p>PHP needs a server to run, so this editor shows syntax highlighting only. Copy the code and run it with <code>php -S localhost:8000</code>, or on any PHP host, to see it execute.</p>",
        'code' => "<?php\n\$name = \"learner\";\necho \"<h1>Hello, \" . \$name . \"!</h1>\";\n?>\n<p>This line is plain HTML.</p>",
      ],
      'forms' => [
        'title' => 'Handling Form Data',
        'summary' => 'Read what a visitor typed once they submit a form.',
        'body' => "<p>When a form's <code>method</code> is <code>POST</code>, the submitted fields arrive in PHP's <code>\$_POST</code> array, keyed by each input's <code>name</code>.</p><p>Save this as <code>handle.php</code> on a PHP server and submit the form to see it run.</p>",
        'code' => "<form method=\"POST\" action=\"handle.php\">\n  <input name=\"username\" placeholder=\"Your name\">\n  <button type=\"submit\">Send</button>\n</form>\n\n<?php\n// handle.php\nif (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n  echo \"Hello, \" . htmlspecialchars(\$_POST['username']);\n}\n?>",
      ],
      'mysql' => [
        'title' => 'Connecting to MySQL',
        'summary' => 'The pattern behind almost every PHP + MySQL app.',
        'body' => "<p>Most PHP sites talk to a MySQL database with <code>mysqli</code> or <code>PDO</code>. The shape is always the same: connect, prepare a query, run it, read the rows.</p><p>This needs a real database to run — copy it into a project connected to MySQL.</p>",
        'code' => "<?php\n\$conn = new mysqli(\"localhost\", \"user\", \"pass\", \"my_db\");\n\$result = \$conn->query(\"SELECT title FROM lessons\");\nwhile (\$row = \$result->fetch_assoc()) {\n  echo \"<li>\" . htmlspecialchars(\$row['title']) . \"</li>\";\n}\n\$conn->close();\n?>",
      ],
    ],
  ],

  'c' => [
    'label' => 'C',
    'course' => 'BSIT',
    'color' => '#5c6bc0',
    'tagline' => 'Close to the machine',
    'runnable' => false,
    'monacoLang' => 'c',
    'filename' => 'main.c',
    'lessons' => [
      'intro' => [
        'title' => 'Hello, World',
        'summary' => 'Every C program starts at main().',
        'body' => "<p>A C program needs a <code>main()</code> function — that's where execution begins. <code>#include &lt;stdio.h&gt;</code> pulls in the standard input/output library so <code>printf</code> is available.</p><p>C is compiled, not interpreted: save this as <code>main.c</code>, then run <code>gcc main.c -o main &amp;&amp; ./main</code> to see it print.</p>",
        'code' => "#include <stdio.h>\n\nint main(void) {\n    printf(\"Hello, World!\\n\");\n    return 0;\n}",
      ],
      'variables' => [
        'title' => 'Variables & Types',
        'summary' => 'C makes you say what kind of data you\'re storing.',
        'body' => "<p>Unlike JavaScript, C requires a type for every variable: <code>int</code> for whole numbers, <code>float</code>/<code>double</code> for decimals, <code>char</code> for a single character. The size is fixed once you choose it.</p>",
        'code' => "#include <stdio.h>\n\nint main(void) {\n    int age = 30;\n    double price = 19.99;\n    char grade = 'A';\n\n    printf(\"Age: %d\\n\", age);\n    printf(\"Price: %.2f\\n\", price);\n    printf(\"Grade: %c\\n\", grade);\n    return 0;\n}",
      ],
      'functions' => [
        'title' => 'Functions & Loops',
        'summary' => 'Reusable blocks of logic, and repeating a block on purpose.',
        'body' => "<p>A function declares the type it returns before its name. A <code>for</code> loop repeats a block a set number of times — useful for anything counted.</p>",
        'code' => "#include <stdio.h>\n\nint square(int n) {\n    return n * n;\n}\n\nint main(void) {\n    for (int i = 1; i <= 5; i++) {\n        printf(\"%d squared is %d\\n\", i, square(i));\n    }\n    return 0;\n}",
      ],
    ],
  ],

  'cpp' => [
    'label' => 'C++',
    'course' => 'BSIT',
    'color' => '#00599c',
    'tagline' => 'C with objects',
    'runnable' => false,
    'monacoLang' => 'cpp',
    'filename' => 'main.cpp',
    'lessons' => [
      'intro' => [
        'title' => 'Hello, World',
        'summary' => 'iostream replaces printf with cout.',
        'body' => "<p>C++ builds on C but adds <code>&lt;iostream&gt;</code> for input/output. <code>std::cout &lt;&lt;</code> sends values to the console; <code>&lt;&lt;</code> chains as many as you like.</p><p>Compile with <code>g++ main.cpp -o main &amp;&amp; ./main</code>.</p>",
        'code' => "#include <iostream>\n\nint main() {\n    std::cout << \"Hello, World!\" << std::endl;\n    return 0;\n}",
      ],
      'classes' => [
        'title' => 'Classes & Objects',
        'summary' => 'Bundle data and the functions that act on it.',
        'body' => "<p>A <code>class</code> groups related data (members) with the functions that operate on it (methods). <code>public:</code> marks what outside code is allowed to touch.</p>",
        'code' => "#include <iostream>\n#include <string>\n\nclass Dog {\npublic:\n    std::string name;\n\n    void bark() {\n        std::cout << name << \" says woof!\" << std::endl;\n    }\n};\n\nint main() {\n    Dog d;\n    d.name = \"Rex\";\n    d.bark();\n    return 0;\n}",
      ],
      'vectors' => [
        'title' => 'Vectors',
        'summary' => 'A resizable array from the standard library.',
        'body' => "<p><code>std::vector</code> is a growable list — like a JavaScript array, but typed. A range-based <code>for</code> loop walks every element without needing an index.</p>",
        'code' => "#include <iostream>\n#include <vector>\n\nint main() {\n    std::vector<int> scores = {90, 85, 77};\n    scores.push_back(100);\n\n    for (int s : scores) {\n        std::cout << s << std::endl;\n    }\n    return 0;\n}",
      ],
    ],
  ],

  'java' => [
    'label' => 'Java',
    'course' => 'BSIT',
    'color' => '#e76f00',
    'tagline' => 'Write once, run anywhere',
    'runnable' => false,
    'monacoLang' => 'java',
    'filename' => 'Main.java',
    'lessons' => [
      'intro' => [
        'title' => 'Hello, World',
        'summary' => 'Every Java file starts with a class.',
        'body' => "<p>Java code always lives inside a <code>class</code>, and execution starts at <code>public static void main(String[] args)</code>. The file name must match the public class name.</p><p>Compile with <code>javac Main.java</code>, then run <code>java Main</code>.</p>",
        'code' => "public class Main {\n    public static void main(String[] args) {\n        System.out.println(\"Hello, World!\");\n    }\n}",
      ],
      'variables' => [
        'title' => 'Variables & Types',
        'summary' => 'Like C, Java wants to know the type up front.',
        'body' => "<p>Common types: <code>int</code>, <code>double</code>, <code>boolean</code>, and <code>String</code> for text (capitalized, since it's a class, not a primitive).</p>",
        'code' => "public class Main {\n    public static void main(String[] args) {\n        int age = 30;\n        double price = 19.99;\n        String name = \"learner\";\n\n        System.out.println(name + \" is \" + age + \" years old.\");\n        System.out.println(\"Price: \" + price);\n    }\n}",
      ],
      'methods' => [
        'title' => 'Methods & Loops',
        'summary' => 'Named blocks of logic, called by other code.',
        'body' => "<p>A method declares a return type, a name, and parameters. <code>static</code> methods belong to the class itself rather than an instance of it.</p>",
        'code' => "public class Main {\n    static int square(int n) {\n        return n * n;\n    }\n\n    public static void main(String[] args) {\n        for (int i = 1; i <= 5; i++) {\n            System.out.println(i + \" squared is \" + square(i));\n        }\n    }\n}",
      ],
    ],
  ],

  'python' => [
    'label' => 'Python',
    'course' => 'BSIT',
    'color' => '#3776ab',
    'tagline' => 'Readable by design',
    'runnable' => false,
    'monacoLang' => 'python',
    'filename' => 'main.py',
    'lessons' => [
      'intro' => [
        'title' => 'Hello, World',
        'summary' => 'No types, no semicolons, no boilerplate.',
        'body' => "<p>Python reads close to plain English. <code>print()</code> writes to the console, and indentation — not braces — defines a block.</p><p>Run it with <code>python3 main.py</code>.</p>",
        'code' => "print(\"Hello, World!\")",
      ],
      'lists' => [
        'title' => 'Lists & Loops',
        'summary' => 'A built-in, flexible collection type.',
        'body' => "<p>A Python list holds any mix of values and grows as needed. <code>for item in list:</code> walks every element directly, without an index variable.</p>",
        'code' => "scores = [90, 85, 77]\nscores.append(100)\n\nfor s in scores:\n    print(s)",
      ],
      'functions' => [
        'title' => 'Functions',
        'summary' => 'Defined with def, no return type declared.',
        'body' => "<p><code>def</code> starts a function definition. Python infers types at runtime, so there's no type to declare up front — just name the parameters.</p>",
        'code' => "def square(n):\n    return n * n\n\nfor i in range(1, 6):\n    print(f\"{i} squared is {square(i)}\")",
      ],
    ],
  ],

  'csharp' => [
    'label' => 'C#',
    'course' => 'BSIT',
    'color' => '#68217a',
    'tagline' => 'Microsoft\'s managed language',
    'runnable' => false,
    'monacoLang' => 'csharp',
    'filename' => 'Program.cs',
    'lessons' => [
      'intro' => [
        'title' => 'Hello, World',
        'summary' => 'Console.WriteLine is C#\'s print statement.',
        'body' => "<p>Modern C# supports a minimal top-level style — no explicit <code>class</code> or <code>Main</code> needed for a simple script. <code>Console.WriteLine</code> prints a line.</p><p>Run it with <code>dotnet run</code> inside a new console project.</p>",
        'code' => "Console.WriteLine(\"Hello, World!\");",
      ],
      'variables' => [
        'title' => 'Variables & Types',
        'summary' => 'Statically typed, with type inference available.',
        'body' => "<p>C# is statically typed like Java, but <code>var</code> lets the compiler infer the type from the assigned value.</p>",
        'code' => "int age = 30;\ndouble price = 19.99;\nvar name = \"learner\"; // inferred as string\n\nConsole.WriteLine($\"{name} is {age} years old.\");\nConsole.WriteLine($\"Price: {price}\");",
      ],
      'methods' => [
        'title' => 'Methods & Loops',
        'summary' => 'Static methods and a classic for loop.',
        'body' => "<p>A <code>static</code> method belongs to the class rather than an instance. String interpolation with <code>$\"...\"</code> embeds expressions directly in text.</p>",
        'code' => "static int Square(int n) => n * n;\n\nfor (int i = 1; i <= 5; i++) {\n    Console.WriteLine($\"{i} squared is {Square(i)}\");\n}",
      ],
    ],
  ],
];

/**
 * ---------------- Admin-editable lesson overrides ----------------
 * Lets an admin add, edit, or delete lessons within any existing category
 * from admin/lessons.php, without touching this file. Stored as JSON via
 * includes/store.php, the same "no database required" pattern used for
 * certificates/config. Shape:
 *   { "html": { "lessons": { "intro": {...} , "new-slug": {...}, "deleted-slug": null } } }
 * A lesson value of null means "deleted" (removed from the built-in set).
 */
function inkwell_lesson_overrides() {
  require_once __DIR__ . '/../includes/store.php';
  $data = inkwell_read_json('lessons_overrides.json', []);
  return is_array($data) ? $data : [];
}

function inkwell_save_lesson_override($cat, $slug, array $lesson) {
  require_once __DIR__ . '/../includes/store.php';
  $overrides = inkwell_lesson_overrides();
  $overrides[$cat]['lessons'][$slug] = $lesson;
  return inkwell_write_json('lessons_overrides.json', $overrides);
}

function inkwell_delete_lesson_override($cat, $slug) {
  require_once __DIR__ . '/../includes/store.php';
  $overrides = inkwell_lesson_overrides();
  $overrides[$cat]['lessons'][$slug] = null;
  return inkwell_write_json('lessons_overrides.json', $overrides);
}

/**
 * Creates or edits a whole track/category (e.g. adds "Ruby" as a new
 * language track) via admin/lessons.php, on top of the built-in
 * $INKWELL_LESSONS list. Built-in categories can't be deleted, only
 * custom ones added here.
 */
function inkwell_save_category_override($catKey, array $meta) {
  require_once __DIR__ . '/../includes/store.php';
  $overrides = inkwell_lesson_overrides();
  $overrides[$catKey]['label'] = $meta['label'];
  $overrides[$catKey]['color'] = $meta['color'];
  $overrides[$catKey]['tagline'] = $meta['tagline'];
  $overrides[$catKey]['runnable'] = !empty($meta['runnable']);
  $overrides[$catKey]['course'] = inkwell_normalize_lesson_course($meta['course'] ?? '');
  if (!isset($overrides[$catKey]['lessons']) || !is_array($overrides[$catKey]['lessons'])) {
    $overrides[$catKey]['lessons'] = [];
  }
  return inkwell_write_json('lessons_overrides.json', $overrides);
}

/** Deletes a custom (non-built-in) track entirely. */
function inkwell_delete_category_override($catKey) {
  global $INKWELL_LESSONS;
  if (isset($INKWELL_LESSONS[$catKey])) return false; // never delete a built-in track
  require_once __DIR__ . '/../includes/store.php';
  $overrides = inkwell_lesson_overrides();
  unset($overrides[$catKey]);
  return inkwell_write_json('lessons_overrides.json', $overrides);
}

/** True if this category exists only as an admin-added track (not built-in). */
function inkwell_is_custom_category($catKey) {
  global $INKWELL_LESSONS;
  return !isset($INKWELL_LESSONS[$catKey]);
}

/** Merges built-in lesson data with admin overrides. Cached per-request. */
function inkwell_categories() {
  global $INKWELL_LESSONS;
  static $merged = null;
  if ($merged !== null) return $merged;

  $merged = $INKWELL_LESSONS;
  foreach (inkwell_lesson_overrides() as $catKey => $catOverride) {
    if (!is_array($catOverride)) continue;
    if (!isset($merged[$catKey])) {
      // Brand-new, admin-created track.
      $merged[$catKey] = [
        'label' => $catOverride['label'] ?? ucfirst($catKey),
        'color' => $catOverride['color'] ?? '#2d5c4c',
        'tagline' => $catOverride['tagline'] ?? '',
        'runnable' => !empty($catOverride['runnable']),
        'course' => inkwell_normalize_lesson_course($catOverride['course'] ?? ''),
        'lessons' => [],
      ];
    } else {
      // Existing built-in track — allow label/color/tagline/course edits too.
      foreach (['label', 'color', 'tagline', 'course'] as $field) {
        if (isset($catOverride[$field])) $merged[$catKey][$field] = $catOverride[$field];
      }
    }
    $merged[$catKey]['course'] = inkwell_normalize_lesson_course($merged[$catKey]['course'] ?? '');
    if (empty($catOverride['lessons']) || !is_array($catOverride['lessons'])) continue;
    foreach ($catOverride['lessons'] as $slug => $lessonData) {
      if ($lessonData === null) {
        unset($merged[$catKey]['lessons'][$slug]);
      } else {
        $merged[$catKey]['lessons'][$slug] = $lessonData;
      }
    }
  }
  return $merged;
}

/**
 * A lesson track's `course` field stores a department *code* (e.g. "BSIT"),
 * matching the same `departments` table used for teachers/deans/subjects
 * (see includes/departments.php) — so adding a department there
 * automatically makes it available here too, no code change needed.
 * Trims/uppercases and falls back to "BSIT" when blank, since every
 * built-in track is a programming lesson.
 */
function inkwell_normalize_lesson_course($code) {
  $code = strtoupper(trim((string) $code));
  return $code !== '' ? $code : 'BSIT';
}

/**
 * Groups every track by department, in department-code order, using the
 * live `departments` table so newly added departments (Registrar/Admin ->
 * Departments) show up automatically. A department with no tracks yet
 * still comes back with an empty array, so the page can render its
 * section (with an empty state) instead of hiding it entirely. Any track
 * whose course code doesn't match a current department (e.g. the
 * department was later renamed/deleted) is bucketed under "Other".
 */
function inkwell_categories_by_course() {
  require_once __DIR__ . '/../includes/departments.php';

  $departments = [];
  try {
    $departments = inkwell_list_departments();
  } catch (Throwable $e) {
    $departments = [];
  }
  if (empty($departments)) {
    // DB unreachable or table not migrated yet — fall back to the seeded set.
    $departments = [
      ['code' => 'BSEED', 'name' => 'Bachelor of Secondary Education'],
      ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
      ['code' => 'BSHM', 'name' => 'Bachelor of Science in Hospitality Management'],
    ];
  }

  $result = [];
  foreach ($departments as $dept) {
    $result[$dept['code']] = ['name' => $dept['name'], 'tracks' => []];
  }

  $knownCodes = array_keys($result);
  foreach (inkwell_categories() as $catKey => $cat) {
    $course = inkwell_normalize_lesson_course($cat['course'] ?? '');
    if (!in_array($course, $knownCodes, true)) $course = '__OTHER__';
    if (!isset($result[$course])) $result[$course] = ['name' => 'Other', 'tracks' => []];
    $result[$course]['tracks'][$catKey] = $cat;
  }

  // Drop the "Other" bucket entirely when it ends up empty.
  if (isset($result['__OTHER__']) && empty($result['__OTHER__']['tracks'])) {
    unset($result['__OTHER__']);
  }

  return $result;
}

/** Return one category's data, or null. */
function inkwell_category($cat) {
  $cats = inkwell_categories();
  return $cats[$cat] ?? null;
}

/** Return one lesson, or null. */
function inkwell_lesson($cat, $slug) {
  $c = inkwell_category($cat);
  if (!$c) return null;
  return $c['lessons'][$slug] ?? null;
}

/** Return [prevSlug, nextSlug] for sequential navigation within a category. */
function inkwell_neighbors($cat, $slug) {
  $c = inkwell_category($cat);
  if (!$c) return [null, null];
  $keys = array_keys($c['lessons']);
  $i = array_search($slug, $keys);
  if ($i === false) return [null, null];
  $prev = $i > 0 ? $keys[$i - 1] : null;
  $next = $i < count($keys) - 1 ? $keys[$i + 1] : null;
  return [$prev, $next];
}
