function toggleChatPopup() {
    console.log('toggleChatPopup called'); // Debug log
    const popup = document.getElementById('chat-popup');
    console.log('Chat popup found:', popup); // Debug log
    
    if (!popup) {
        console.log('Chat popup element not found!'); // Debug log
        return;
    }
    
    popup.classList.toggle('show'); // Changed from 'active' to 'show'
    console.log('Chat popup classes after toggle:', popup.className); // Debug log
    
    if (popup.classList.contains('show')) {
        console.log('Loading messages...'); // Debug log
        loadMessages();
        setTimeout(() => {
            const chatInput = document.getElementById('chat-input');
            if (chatInput) {
                chatInput.focus();
            }
        }, 100);
    }
}

// Load messages via AJAX with loading indicator
function loadMessages() {
    const messagesDiv = document.getElementById('chat-messages');
    messagesDiv.innerHTML = '<div class="loading-indicator">Loading messages...</div>';
    
    fetch('chat_fetch.php')
        .then(res => res.json())
        .then(data => {
            messagesDiv.innerHTML = '';
            data.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'chat-message ' + (msg.is_user ? 'user' : 'admin');
                div.textContent = msg.body;
                messagesDiv.appendChild(div);
            });
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        })
        .catch(error => {
            messagesDiv.innerHTML = '<div class="error-message">Failed to load messages. Please try again.</div>';
            console.error('Error loading messages:', error);
        });
}

// Send message via AJAX with improved UX
document.addEventListener('DOMContentLoaded', function() {
    console.log('Chat.js DOM loaded'); // Debug log
    
    // Set up help button and close button event listeners
    const helpBtn = document.querySelector('.help-btn');
    const closeChatBtn = document.querySelector('.close-chat');
    
    console.log('Help button found:', helpBtn); // Debug log
    console.log('Close chat button found:', closeChatBtn); // Debug log
    
    if (helpBtn) {
        console.log('Adding click listener to help button'); // Debug log
        helpBtn.addEventListener('click', function(e) {
            console.log('Help button clicked!'); // Debug log
            e.preventDefault();
            toggleChatPopup();
        });
    } else {
        console.log('Help button not found!'); // Debug log
    }
    
    if (closeChatBtn) {
        console.log('Adding click listener to close button'); // Debug log
        closeChatBtn.addEventListener('click', function(e) {
            console.log('Close button clicked!'); // Debug log
            e.preventDefault();
            toggleChatPopup();
        });
    }
    
    const form = document.getElementById('chat-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const button = this.querySelector('button[type="submit"]');
            const msg = input.value.trim();
            
            if (!msg) return;
            
            // Show loading state
            const originalText = button.textContent;
            button.textContent = 'Sending...';
            button.disabled = true;
            
            fetch('chat_send.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'message=' + encodeURIComponent(msg)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadMessages();
                } else {
                    throw new Error(data.error || 'Failed to send message');
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('Failed to send message. Please try again.');
            })
            .finally(() => {
                button.textContent = originalText;
                button.disabled = false;
                input.focus();
            });
        });
    }
    // Optional: Poll for new messages every 5 seconds
    setInterval(() => {
        const popup = document.getElementById('chat-popup');
        if (popup && popup.classList.contains('show')) {
            loadMessages();
        }
    }, 5000);
});