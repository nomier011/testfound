<?php
require_once 'config.php';
$page_title = 'Coding Practice';
if (!isLoggedIn()) redirect('login.php');
$user = getUserById($_SESSION['user_id']);
$conn = getConnection();

// ── Handle AJAX code execution ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_code'])) {
    header('Content-Type: application/json');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['output' => 'Error: Invalid request token.', 'error' => true]);
        exit();
    }

    $code     = $_POST['code']     ?? '';
    $language = $_POST['language'] ?? 'javascript';

    // Only JavaScript runs client-side; PHP/Python/HTML run server-side here
    // For safety, only allow JS execution via client-side eval (returned as-is)
    // PHP execution is sandboxed via eval with output buffering
    // Python is not available on most shared hosts — return a helpful message

    $output = '';
    $is_error = false;

    if ($language === 'php') {
        // Strip opening tags if present
        $code = preg_replace('/^<\?php\s*/i', '', trim($code));
        $code = preg_replace('/\?>$/', '', $code);

        // Block dangerous functions
        $blocked = ['exec','shell_exec','system','passthru','popen','proc_open',
                    'file_put_contents','file_get_contents','unlink','rmdir',
                    'mkdir','rename','copy','move_uploaded_file','include',
                    'require','include_once','require_once','eval','base64_decode',
                    'preg_replace','create_function','call_user_func','header',
                    'setcookie','session_','phpinfo','dl','pcntl_','posix_'];
        foreach ($blocked as $fn) {
            if (stripos($code, $fn) !== false) {
                echo json_encode(['output' => "⛔ Function '$fn' is not allowed in the sandbox.", 'error' => true]);
                exit();
            }
        }

        ob_start();
        try {
            // Wrap in a function scope to prevent variable leakage
            $fn = create_function('', $code);
            if ($fn) {
                $fn();
            } else {
                echo "Syntax error in code.";
            }
        } catch (Throwable $e) {
            echo "Error: " . $e->getMessage();
            $is_error = true;
        }
        $output = ob_get_clean();
        if (empty(trim($output))) $output = "(No output)";

    } elseif ($language === 'html') {
        // Return the HTML as-is for client-side rendering in an iframe
        echo json_encode(['output' => $code, 'html' => true, 'error' => false]);
        exit();

    } elseif ($language === 'javascript') {
        // JS runs client-side — just echo back the code for the browser to eval
        echo json_encode(['output' => $code, 'js' => true, 'error' => false]);
        exit();

    } elseif ($language === 'python') {
        echo json_encode(['output' => "🐍 Python execution requires a server-side Python runtime.\nThis sandbox supports JavaScript, PHP, and HTML.\n\nTip: You can test Python code at https://replit.com or https://python.org/shell", 'error' => true]);
        exit();
    }

    echo json_encode(['output' => $output, 'error' => $is_error]);
    exit();
}

// ── Load exercises by subject/language ───────────────────────────────────────
$exercises = [
    'javascript' => [
        [
            'title'       => 'Hello World',
            'description' => 'Print "Hello, World!" to the console.',
            'starter'     => "// Write your code below\nconsole.log('Hello, World!');",
            'hint'        => 'Use console.log() to print output.',
        ],
        [
            'title'       => 'Sum of Two Numbers',
            'description' => 'Write a function that returns the sum of two numbers.',
            'starter'     => "function sum(a, b) {\n  // your code here\n  return a + b;\n}\n\nconsole.log(sum(3, 5)); // Expected: 8",
            'hint'        => 'Use the + operator to add numbers.',
        ],
        [
            'title'       => 'FizzBuzz',
            'description' => 'Print numbers 1–20. For multiples of 3 print "Fizz", multiples of 5 print "Buzz", both print "FizzBuzz".',
            'starter'     => "for (let i = 1; i <= 20; i++) {\n  // your code here\n}",
            'hint'        => 'Use the modulo operator (%) to check divisibility.',
        ],
        [
            'title'       => 'Reverse a String',
            'description' => 'Write a function that reverses a string.',
            'starter'     => "function reverseString(str) {\n  // your code here\n}\n\nconsole.log(reverseString('hello')); // Expected: 'olleh'",
            'hint'        => 'Try split(), reverse(), and join().',
        ],
        [
            'title'       => 'Find the Largest Number',
            'description' => 'Write a function that returns the largest number in an array.',
            'starter'     => "function findMax(arr) {\n  // your code here\n}\n\nconsole.log(findMax([3, 7, 2, 9, 1])); // Expected: 9",
            'hint'        => 'Try Math.max(...arr) or a loop.',
        ],
    ],
    'php' => [
        [
            'title'       => 'Hello World',
            'description' => 'Print "Hello, World!" using PHP.',
            'starter'     => "echo 'Hello, World!';",
            'hint'        => 'Use echo or print to output text.',
        ],
        [
            'title'       => 'Sum of Array',
            'description' => 'Calculate the sum of all numbers in an array.',
            'starter'     => "\$numbers = [1, 2, 3, 4, 5];\n// Calculate and echo the sum\n\$sum = array_sum(\$numbers);\necho \"Sum: \$sum\";",
            'hint'        => 'Use array_sum() or a foreach loop.',
        ],
        [
            'title'       => 'FizzBuzz',
            'description' => 'Print FizzBuzz for numbers 1–20.',
            'starter'     => "for (\$i = 1; \$i <= 20; \$i++) {\n  // your code here\n  echo \$i . \"\\n\";\n}",
            'hint'        => 'Use the modulo operator % to check divisibility.',
        ],
        [
            'title'       => 'Palindrome Check',
            'description' => 'Check if a string is a palindrome.',
            'starter'     => "function isPalindrome(\$str) {\n  // your code here\n  return strrev(\$str) === \$str;\n}\n\necho isPalindrome('racecar') ? 'Yes' : 'No';",
            'hint'        => 'Use strrev() to reverse a string.',
        ],
    ],
    'html' => [
        [
            'title'       => 'Basic Page',
            'description' => 'Create a basic HTML page with a heading and paragraph.',
            'starter'     => "<!DOCTYPE html>\n<html>\n<head>\n  <title>My Page</title>\n</head>\n<body>\n  <h1>Hello World</h1>\n  <p>This is my first HTML page.</p>\n</body>\n</html>",
            'hint'        => 'Use <h1> for headings and <p> for paragraphs.',
        ],
        [
            'title'       => 'Styled Button',
            'description' => 'Create a styled button using inline CSS.',
            'starter'     => "<!DOCTYPE html>\n<html>\n<body>\n  <button style=\"background:#dc2626; color:white; padding:12px 24px; border:none; border-radius:8px; font-size:16px; cursor:pointer;\">\n    Click Me!\n  </button>\n</body>\n</html>",
            'hint'        => 'Use the style attribute for inline CSS.',
        ],
        [
            'title'       => 'Simple Form',
            'description' => 'Build a simple login form with username and password fields.',
            'starter'     => "<!DOCTYPE html>\n<html>\n<body>\n  <form>\n    <label>Username: <input type=\"text\" name=\"username\"></label><br><br>\n    <label>Password: <input type=\"password\" name=\"password\"></label><br><br>\n    <button type=\"submit\">Login</button>\n  </form>\n</body>\n</html>",
            'hint'        => 'Use <input type="text"> and <input type="password">.',
        ],
    ],
];
?>
<?php include 'header.php'; ?>

<div class="page-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="page-hero">
        <div class="page-hero-content">
            <h1><i class="fas fa-code"></i> Coding Practice</h1>
            <p>Write, run, and learn — right in your browser</p>
        </div>
    </div>

    <div class="page-inner">

        <!-- Language Tabs -->
        <div class="lang-tabs">
            <button class="lang-tab active" data-lang="javascript" onclick="switchLang('javascript', this)">
                <i class="fab fa-js-square"></i> JavaScript
            </button>
            <button class="lang-tab" data-lang="php" onclick="switchLang('php', this)">
                <i class="fab fa-php"></i> PHP
            </button>
            <button class="lang-tab" data-lang="html" onclick="switchLang('html', this)">
                <i class="fab fa-html5"></i> HTML
            </button>
            <button class="lang-tab" data-lang="python" onclick="switchLang('python', this)">
                <i class="fab fa-python"></i> Python
            </button>
        </div>

        <div class="practice-layout">

            <!-- Left: Exercises List -->
            <div class="exercises-panel">
                <h3 style="font-size:.9rem;font-weight:700;color:#111;margin-bottom:14px;">
                    <i class="fas fa-list-ul"></i> Exercises
                </h3>
                <div id="exerciseList"></div>

                <!-- Free Practice -->
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;">
                    <button onclick="loadFreeCode()" class="exercise-item" style="width:100%;text-align:left;background:linear-gradient(135deg,#dc2626,#1e3a8a);color:#fff;border:none;cursor:pointer;">
                        <div style="font-weight:700;font-size:.85rem;"><i class="fas fa-edit"></i> Free Practice</div>
                        <div style="font-size:.72rem;opacity:.85;margin-top:2px;">Write your own code from scratch</div>
                    </button>
                </div>
            </div>

            <!-- Right: Editor + Output -->
            <div class="editor-panel">

                <!-- Exercise Description -->
                <div id="exerciseDesc" class="exercise-desc" style="display:none;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <span id="descTitle" style="font-weight:700;font-size:1rem;color:#111;"></span>
                        <button onclick="showHint()" style="background:#fef9c3;border:1px solid #fde68a;color:#92400e;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;cursor:pointer;"><i class="fas fa-lightbulb"></i> Hint</button>
                    </div>
                    <p id="descText" style="font-size:.85rem;color:#4b5563;margin:0;"></p>
                    <div id="hintBox" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-top:10px;font-size:.82rem;color:#92400e;"></div>
                </div>

                <!-- Code Editor -->
                <div class="editor-toolbar">
                    <span id="langLabel" style="font-size:.8rem;font-weight:700;color:#6b7280;"><i class="fab fa-js-square"></i> JavaScript</span>
                    <div style="display:flex;gap:8px;">
                        <button onclick="clearCode()" style="background:#f3f4f6;border:none;padding:6px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;color:#374151;"><i class="fas fa-trash"></i> Clear</button>
                        <button onclick="copyCode()" style="background:#f3f4f6;border:none;padding:6px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;color:#374151;"><i class="fas fa-copy"></i> Copy</button>
                        <button onclick="runCode()" id="runBtn" style="background:#16a34a;color:#fff;border:none;padding:6px 18px;border-radius:6px;font-size:.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
                            <i class="fas fa-play"></i> Run
                        </button>
                    </div>
                </div>

                <textarea id="codeEditor" class="code-editor" spellcheck="false" placeholder="// Write your code here..."></textarea>

                <!-- Output -->
                <div class="output-panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#1e1e2e;border-radius:10px 10px 0 0;">
                        <span style="font-size:.78rem;font-weight:700;color:#cdd6f4;"><i class="fas fa-terminal"></i> Output</span>
                        <button onclick="clearOutput()" style="background:none;border:none;color:#6c7086;font-size:.72rem;cursor:pointer;">Clear</button>
                    </div>
                    <div id="outputArea" class="output-area">Run your code to see output here...</div>
                    <!-- HTML preview iframe -->
                    <iframe id="htmlPreview" style="display:none;width:100%;height:300px;border:none;border-radius:0 0 10px 10px;background:#fff;"></iframe>
                </div>

            </div>
        </div>

    </div>
</div>

<style>
.lang-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.lang-tab {
    padding: 10px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    font-size: .85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
    display: flex;
    align-items: center;
    gap: 7px;
    color: #374151;
}
.lang-tab:hover { border-color: #dc2626; color: #dc2626; }
.lang-tab.active { background: #dc2626; color: #fff; border-color: #dc2626; }
.lang-tab .fab { font-size: 1rem; }

.practice-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 20px;
    align-items: start;
}

.exercises-panel {
    background: rgba(255,255,255,.92);
    border-radius: 14px;
    padding: 16px;
    border: 1px solid rgba(255,255,255,.4);
    position: sticky;
    top: 20px;
}

.exercise-item {
    display: block;
    width: 100%;
    text-align: left;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}
.exercise-item:hover { border-color: #dc2626; background: #fef2f2; }
.exercise-item.active { border-color: #dc2626; background: #fef2f2; }
.exercise-item .ex-title { font-weight: 700; font-size: .85rem; color: #111; }
.exercise-item .ex-desc  { font-size: .72rem; color: #6b7280; margin-top: 2px; }

.editor-panel {
    background: rgba(255,255,255,.92);
    border-radius: 14px;
    padding: 20px;
    border: 1px solid rgba(255,255,255,.4);
}

.exercise-desc {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 14px;
}

.editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.code-editor {
    width: 100%;
    min-height: 280px;
    background: #1e1e2e;
    color: #cdd6f4;
    font-family: 'Fira Code', 'Courier New', monospace;
    font-size: .9rem;
    line-height: 1.6;
    padding: 16px;
    border: none;
    border-radius: 10px;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    tab-size: 2;
}

.output-panel {
    margin-top: 14px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #2a2a3e;
}

.output-area {
    background: #1e1e2e;
    color: #a6e3a1;
    font-family: 'Fira Code', 'Courier New', monospace;
    font-size: .85rem;
    padding: 16px;
    min-height: 120px;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
    border-radius: 0 0 10px 10px;
}
.output-area.error { color: #f38ba8; }

@media (max-width: 768px) {
    .practice-layout { grid-template-columns: 1fr; }
    .exercises-panel { position: static; }
}
</style>

<script>
const exercises = <?php echo json_encode($exercises); ?>;
const csrfToken = '<?php echo htmlspecialchars(generateCsrfToken()); ?>';

let currentLang    = 'javascript';
let currentHint    = '';
let currentExIndex = -1;

// ── Language icons & labels ───────────────────────────────────────────────────
const langMeta = {
    javascript: { icon: 'fab fa-js-square',  label: 'JavaScript', color: '#f7df1e' },
    php:        { icon: 'fab fa-php',         label: 'PHP',        color: '#8892be' },
    html:       { icon: 'fab fa-html5',       label: 'HTML',       color: '#e34f26' },
    python:     { icon: 'fab fa-python',      label: 'Python',     color: '#3572a5' },
};

function switchLang(lang, btn) {
    currentLang = lang;
    currentExIndex = -1;

    document.querySelectorAll('.lang-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const meta = langMeta[lang];
    document.getElementById('langLabel').innerHTML = `<i class="${meta.icon}" style="color:${meta.color}"></i> ${meta.label}`;

    renderExercises();
    clearOutput();
    document.getElementById('exerciseDesc').style.display = 'none';

    // Default starter
    if (lang === 'python') {
        document.getElementById('codeEditor').value = '# Python code runs on your local machine\n# This sandbox shows a preview only\nprint("Hello, World!")';
    } else if (exercises[lang] && exercises[lang].length > 0) {
        loadExercise(0);
    } else {
        document.getElementById('codeEditor').value = '';
    }
}

function renderExercises() {
    const list = document.getElementById('exerciseList');
    list.innerHTML = '';
    const exs = exercises[currentLang] || [];
    exs.forEach((ex, i) => {
        const btn = document.createElement('button');
        btn.className = 'exercise-item' + (i === currentExIndex ? ' active' : '');
        btn.innerHTML = `<div class="ex-title">${i+1}. ${ex.title}</div><div class="ex-desc">${ex.description.substring(0,50)}...</div>`;
        btn.onclick = () => loadExercise(i);
        list.appendChild(btn);
    });
}

function loadExercise(index) {
    const exs = exercises[currentLang] || [];
    if (!exs[index]) return;
    currentExIndex = index;

    const ex = exs[index];
    document.getElementById('codeEditor').value = ex.starter;
    document.getElementById('descTitle').textContent = ex.title;
    document.getElementById('descText').textContent  = ex.description;
    document.getElementById('exerciseDesc').style.display = 'block';
    document.getElementById('hintBox').style.display = 'none';
    currentHint = ex.hint;

    clearOutput();
    renderExercises();
}

function loadFreeCode() {
    currentExIndex = -1;
    document.getElementById('exerciseDesc').style.display = 'none';
    const defaults = {
        javascript: '// Free practice — write any JavaScript\nconsole.log("Hello!");',
        php:        "// Free practice — write any PHP\necho 'Hello!';",
        html:       '<!DOCTYPE html>\n<html>\n<body>\n  <h1>Hello!</h1>\n</body>\n</html>',
        python:     '# Free practice\nprint("Hello!")',
    };
    document.getElementById('codeEditor').value = defaults[currentLang] || '';
    clearOutput();
    renderExercises();
}

function showHint() {
    const box = document.getElementById('hintBox');
    box.textContent = '💡 ' + currentHint;
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
}

function clearCode() {
    document.getElementById('codeEditor').value = '';
}

function copyCode() {
    const code = document.getElementById('codeEditor').value;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => btn.innerHTML = orig, 1500);
    });
}

function clearOutput() {
    const out = document.getElementById('outputArea');
    out.textContent = 'Run your code to see output here...';
    out.className = 'output-area';
    document.getElementById('htmlPreview').style.display = 'none';
    out.style.display = 'block';
}

// ── Tab key support in editor ─────────────────────────────────────────────────
document.getElementById('codeEditor').addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end   = this.selectionEnd;
        this.value  = this.value.substring(0, start) + '  ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 2;
    }
});

// ── Run code ──────────────────────────────────────────────────────────────────
function runCode() {
    const code   = document.getElementById('codeEditor').value.trim();
    const btn    = document.getElementById('runBtn');
    const output = document.getElementById('outputArea');

    if (!code) {
        output.textContent = 'Nothing to run — write some code first!';
        output.className = 'output-area error';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';
    output.textContent = 'Running...';
    output.className = 'output-area';

    // JavaScript — run client-side using a sandboxed approach
    if (currentLang === 'javascript') {
        runJavaScript(code);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run';
        return;
    }

    // PHP / HTML / Python — send to server
    const formData = new FormData();
    formData.append('run_code', '1');
    formData.append('csrf_token', csrfToken);
    formData.append('code', code);
    formData.append('language', currentLang);

    fetch('coding_practice.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Run';

            if (data.html) {
                // Render HTML in iframe
                output.style.display = 'none';
                const iframe = document.getElementById('htmlPreview');
                iframe.style.display = 'block';
                iframe.srcdoc = data.output;
            } else {
                output.style.display = 'block';
                document.getElementById('htmlPreview').style.display = 'none';
                output.textContent = data.output || '(No output)';
                output.className = 'output-area' + (data.error ? ' error' : '');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play"></i> Run';
            output.textContent = 'Network error: ' + err.message;
            output.className = 'output-area error';
        });
}

// ── Sandboxed JavaScript execution ───────────────────────────────────────────
function runJavaScript(code) {
    const output = document.getElementById('outputArea');
    output.style.display = 'block';
    document.getElementById('htmlPreview').style.display = 'none';

    const logs = [];
    const origLog   = console.log;
    const origWarn  = console.warn;
    const origError = console.error;

    // Override console methods
    console.log   = (...args) => logs.push(args.map(formatVal).join(' '));
    console.warn  = (...args) => logs.push('⚠️ ' + args.map(formatVal).join(' '));
    console.error = (...args) => logs.push('❌ ' + args.map(formatVal).join(' '));

    let isError = false;
    try {
        // Use Function constructor for slightly better scoping than eval
        const fn = new Function(code);
        fn();
    } catch (e) {
        logs.push('❌ ' + e.toString());
        isError = true;
    } finally {
        console.log   = origLog;
        console.warn  = origWarn;
        console.error = origError;
    }

    output.textContent = logs.length ? logs.join('\n') : '(No output — use console.log() to print)';
    output.className = 'output-area' + (isError ? ' error' : '');
}

function formatVal(v) {
    if (v === null)      return 'null';
    if (v === undefined) return 'undefined';
    if (typeof v === 'object') {
        try { return JSON.stringify(v, null, 2); } catch { return String(v); }
    }
    return String(v);
}

// ── Init ──────────────────────────────────────────────────────────────────────
renderExercises();
if (exercises['javascript'] && exercises['javascript'].length > 0) {
    loadExercise(0);
}
</script>

<?php include 'footer.php'; ?>
