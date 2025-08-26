<?php
session_start();
include "../includes/db_connect.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];

// Fetch all users who have messaged the admin
$users_stmt = $conn->prepare("
    SELECT u.user_id, u.username, MAX(m.sent_at) AS last_msg
    FROM messages m
    JOIN users u ON (u.user_id = m.sender_id OR u.user_id = m.receiver_id)
    WHERE (
        (m.sender_id IN (SELECT user_id FROM users WHERE role = 'admin') AND u.role = 'user')
        OR
        (m.receiver_id IN (SELECT user_id FROM users WHERE role = 'admin') AND u.role = 'user')
    )
    GROUP BY u.user_id, u.username
    ORDER BY last_msg DESC
");
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}
$users_stmt->close();

$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : (count($users) ? $users[0]['user_id'] : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Messenger</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body {
            background:rgb(255, 255, 255);
            margin: 0;
            padding-top: 70px; /* Add this line to push content below the fixed header */
        }
        .messenger-container {
            max-width: 900px;
            margin: 40px auto 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(232,139,155,0.13);
            display: flex;
            min-height: 600px;
            max-height: 700px; /* Add this line for fixed height */
            overflow: hidden;
        }
        .user-list-panel {
            width: 260px;
            background: #e88b9b;
            color: #fff;
            padding: 0;
            border-right: 2px solid #f7d3db;
            display: flex;
            flex-direction: column;
            height: 700px; /* Add this line for fixed height */
        }
        .user-list-title {
            font-size: 1.2rem;
            font-weight: bold;
            padding: 18px 20px 10px 20px;
            border-bottom: 1.5px solid #f7d3db;
            letter-spacing: 1px;
        }
        .user-list {
            flex: 1;
            overflow-y: auto;
            padding: 0 0 10px 0;
            max-height: 600px; /* Add this line for scrollable user list */
        }
        .user-btn {
            display: flex;
            align-items: center;
            width: 100%;
            background: none;
            border: none;
            color: #fff;
            font-size: 1rem;
            padding: 14px 24px;
            cursor: pointer;
            text-align: left;
            transition: background 0.18s;
            border-bottom: 1px solid #f7d3db;
            outline: none;
        }
        .user-btn.selected, .user-btn:hover {
            background: #fff;
            color: #e88b9b;
            font-weight: bold;
        }
        .messenger-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
            height: 700px; /* Add this line for fixed height */
        }
        .messenger-header {
            padding: 18px 24px;
            border-bottom: 1.5px solid #f7d3db;
            background: #fff;
            font-size: 1.15rem;
            font-weight: bold;
            color: #e88b9b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .msg-list {
            flex: 1;
            padding: 24px 24px 12px 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #f7d3db;
            max-height: 500px; /* Add this line for scrollable chat */
            min-height: 300px; /* Optional: minimum height */
        }
        .msg-item {
            max-width: 60%;
            padding: 10px 16px;
            border-radius: 16px;
            font-size: 1rem;
            word-break: break-word;
            box-shadow: 0 1px 3px rgba(232,139,155,0.07);
            position: relative;
            margin-bottom: 2px;
            display: flex;
            flex-direction: column;
        }
        .msg-item.admin {
            align-self: flex-end;
            background: #e88b9b;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .msg-item.user {
            align-self: flex-start;
            background: #fff;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        .msg-item .date {
            font-size: 0.85em;
            color: #888;
            margin-top: 4px;
            align-self: flex-end;
        }
        .reply-form {
            display: flex;
            gap: 8px;
            padding: 18px 24px;
            border-top: 1.5px solid #f7d3db;
            background: #fff;
        }
        .reply-form input[type="text"] {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            border: 1.5px solid #e88b9b;
            font-size: 1rem;
            outline: none;
            background: #f7d3db;
        }
        .reply-form button {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            background: #e88b9b;
            color: #fff;
            font-weight: bold;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .reply-form button:hover { background: #c47181; }
        @media (max-width: 900px) {
            .messenger-container { flex-direction: column; min-height: 500px; }
            .user-list-panel { width: 100%; flex-direction: row; border-right: none; border-bottom: 2px solid #f7d3db; }
            .user-list-title { display: none; }
            .user-list { display: flex; flex-direction: row; overflow-x: auto; overflow-y: hidden; }
            .user-btn { border-bottom: none; border-right: 1px solid #f7d3db; padding: 12px 16px; }
        }
        @media (max-width: 600px) {
            .messenger-container { min-height: 400px; }
            .msg-list { padding: 10px 4vw 8px 4vw; }
            .messenger-header, .reply-form { padding: 10px 4vw; }
        }
    </style>
</head>
<body>
    <header>
        <h1 class="shop-title">Admin Messenger</h1>
        <a href="dashboard.php" class="back-button">← Back to Dashboard</a>
    </header>
    <div class="messenger-container">
        <aside class="user-list-panel">
            <div class="user-list-title">Users</div>
            <div class="user-list" id="user-list">
                <!-- User buttons will be loaded here by JS -->
            </div>
        </aside>
        <section class="messenger-main">
            <div class="messenger-header">
                <?php
                $selected_user = null;
                foreach ($users as $u) {
                    if ($u['user_id'] == $selected_user_id) $selected_user = $u;
                }
                ?>
                <?= $selected_user ? 'Chat with <span style="color:#c47181;">' . htmlspecialchars($selected_user['username']) . '</span>' : 'No user selected' ?>
            </div>
            <div class="msg-list" id="msg-list">
                <!-- Messages will be loaded here by JS -->
            </div>
            <?php if ($selected_user_id): ?>
            <form class="reply-form" id="reply-form" autocomplete="off" action="javascript:void(0);">
                <input type="hidden" name="reply_to_user" id="reply_to_user" value="<?= $selected_user_id ?>">
                <input type="text" name="reply_message" id="reply_message" placeholder="Type your reply..." required autocomplete="off">
                <button type="submit">Send</button>
            </form>
            <?php endif; ?>
        </section>
    </div>
    <script>
    // Load messages via AJAX
    function loadAdminMessages() {
        const userId = <?= $selected_user_id ?: 0 ?>;
        if (!userId) {
            document.getElementById('msg-list').innerHTML = '<div style="color:#888;">No user conversations yet.</div>';
            return;
        }
        fetch('messages_fetch.php?user_id=' + userId)
            .then(res => res.json())
            .then(data => {
                const msgList = document.getElementById('msg-list');
                msgList.innerHTML = '';
                if (!data.length) {
                    msgList.innerHTML = '<div style="color:#888;">No messages yet.</div>';
                } else {
                    data.forEach(msg => {
                        const div = document.createElement('div');
                        div.className = 'msg-item ' + (msg.is_admin ? 'admin' : 'user');
                        div.innerHTML = `<div>${msg.body}</div><div class="date">${msg.username} | ${msg.sent_at}</div>`;
                        msgList.appendChild(div);
                    });
                    msgList.scrollTop = msgList.scrollHeight;
                }
            });
    }

    // Load user list via AJAX
    function loadUserList() {
        fetch('messages_users.php')
            .then(res => res.json())
            .then(users => {
                const userList = document.getElementById('user-list');
                userList.innerHTML = '';
                users.forEach(user => {
                    const a = document.createElement('a');
                    a.href = '?user_id=' + user.user_id;
                    const btn = document.createElement('button');
                    btn.className = 'user-btn' + (user.user_id == <?= $selected_user_id ?> ? ' selected' : '');
                    btn.innerHTML = `
                        ${user.username}
                        ${user.unread_count > 0 ? `<span style="background:#fff;color:#e88b9b;border-radius:10px;padding:2px 8px;margin-left:8px;font-size:0.9em;">${user.unread_count}</span>` : ''}
                    `;
                    a.appendChild(btn);
                    userList.appendChild(a);
                });
            });
    }

    // Send reply via AJAX
    document.addEventListener('DOMContentLoaded', function() {
        loadAdminMessages();
        loadUserList();
        const form = document.getElementById('reply-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const msg = document.getElementById('reply_message').value.trim();
                const userId = document.getElementById('reply_to_user').value;
                if (!msg) return;
                fetch('messages_send.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'reply_message=' + encodeURIComponent(msg) + '&reply_to_user=' + encodeURIComponent(userId)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('reply_message').value = '';
                        loadAdminMessages();
                        loadUserList();
                    }
                });
            });
        }
        setInterval(() => {
            loadAdminMessages();
            loadUserList();
        }, 5000);
    });
    </script>
</body>
</html>