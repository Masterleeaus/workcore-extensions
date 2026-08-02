// Laravel Chat Application JavaScript
$(document).ready(function () {
    // Configuration
    const API_BASE = '/api';
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

    // State
    let currentConversationId = null;
    let currentUser = null;
    let conversations = [];
    let users = [];
    let typingTimer = null;
    let pusher = null;
    let echo = null;

    // Initialize
    init();

    function init() {
        setupAxios();
        getCurrentUser();
        loadConversations();
        loadUsers();
        setupEventListeners();
        initializePusher();
    }

    function setupAxios() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json'
            }
        });
    }

    function getCurrentUser() {
        $.get(`${API_BASE}/user`, function (user) {
            currentUser = user;
            console.log('Current user:', currentUser);
        });
    }

    function initializePusher() {
        // Initialize Laravel Echo with Pusher
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env("PUSHER_APP_KEY") }}',
            cluster: '{{ env("PUSHER_APP_CLUSTER") }}',
            forceTLS: true,
            encrypted: true
        });

        console.log('Pusher initialized');
    }

    function loadConversations() {
        $.get(`${API_BASE}/conversations`, function (data) {
            conversations = data;
            renderConversations(data);
        }).fail(function (error) {
            console.error('Error loading conversations:', error);
        });
    }

    function renderConversations(convs) {
        const container = $('#conversations-list');
        container.empty();

        if (convs.length === 0) {
            container.html('<div class="text-center text-gray-500 p-4 text-sm">No conversations yet</div>');
            return;
        }

        convs.forEach(conv => {
            const isActive = conv.id === currentConversationId ? 'active' : '';
            const avatar = conv.avatar || (conv.type === 'p2p' && conv.participants.length > 0
                ? `https://ui-avatars.com/api/?name=${conv.participants[0].name}&background=724FF9&color=fff`
                : 'https://ui-avatars.com/api/?name=Group&background=8b5cf6&color=fff');

            const displayName = conv.name || (conv.type === 'p2p' && conv.participants.length > 0
                ? conv.participants.find(p => p.id !== currentUser.id)?.name || 'Unknown'
                : 'Group Chat');

            const lastMessage = conv.latest_message
                ? conv.latest_message.message.substring(0, 30) + '...'
                : 'No messages yet';

            const time = conv.latest_message
                ? formatTime(new Date(conv.latest_message.created_at))
                : '';

            const typeBadge = conv.type === 'group'
                ? '<i data-lucide="users" class="w-2.5 h-2.5"></i>'
                : '<i data-lucide="user" class="w-2.5 h-2.5"></i>';

            const html = `
                <div class="chat-item ${isActive}" data-conversation-id="${conv.id}" data-type="${conv.type}">
                    <div class="relative">
                        <img src="${avatar}" alt="${displayName}" class="chat-avatar">
                        <span class="chat-type-badge ${conv.type}">
                            ${typeBadge}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <h5 class="font-semibold text-gray-900 text-sm">${escapeHtml(displayName)}</h5>
                            <span class="text-xs text-gray-400">${time}</span>
                        </div>
                        <p class="text-xs text-gray-500 truncate">${escapeHtml(lastMessage)}</p>
                    </div>
                </div>
            `;

            container.append(html);
        });

        lucide.createIcons();
        bindConversationClick();
    }

    function bindConversationClick() {
        $('.chat-item').off('click').on('click', function () {
            const conversationId = $(this).data('conversation-id');
            selectConversation(conversationId);
        });
    }

    function selectConversation(conversationId) {
        currentConversationId = conversationId;

        $('.chat-item').removeClass('active');
        $(`.chat-item[data-conversation-id="${conversationId}"]`).addClass('active');

        loadMessages(conversationId);
        subscribeToConversation(conversationId);

        const conversation = conversations.find(c => c.id === conversationId);
        if (conversation) {
            const displayName = conversation.name || (conversation.type === 'p2p' && conversation.participants.length > 0
                ? conversation.participants.find(p => p.id !== currentUser.id)?.name || 'Unknown'
                : 'Group Chat');
            $('#chat-header-title').text(displayName);
        }
    }

    function loadMessages(conversationId) {
        $.get(`${API_BASE}/conversations/${conversationId}/messages`, function (messages) {
            renderMessages(messages);
        }).fail(function (error) {
            console.error('Error loading messages:', error);
        });
    }

    function renderMessages(messages) {
        const container = $('#messages-container');
        container.empty();

        if (messages.length === 0) {
            container.html(`
                <div class="text-center text-gray-500 mt-20">
                    <i data-lucide="message-circle" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `);
            lucide.createIcons();
            return;
        }

        messages.forEach(msg => {
            appendMessage(msg);
        });

        scrollToBottom();
    }

    function appendMessage(message) {
        const container = $('#messages-container');
        const isSent = message.user_id === currentUser.id;
        const time = formatTime(new Date(message.created_at));
        const avatar = `https://ui-avatars.com/api/?name=${message.user.name}&background=724FF9&color=fff`;

        let html;
        if (isSent) {
            html = `
                <div class="flex items-end justify-end space-x-3">
                    <div class="flex-1 flex flex-col items-end">
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="text-xs text-gray-400">You, ${time}</span>
                        </div>
                        <div class="message-bubble sent">
                            ${escapeHtml(message.message)}
                        </div>
                    </div>
                </div>
            `;
        } else {
            html = `
                <div class="flex items-start space-x-3">
                    <img src="${avatar}" alt="${message.user.name}" class="w-8 h-8 rounded-full flex-shrink-0">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="text-sm font-semibold text-gray-900">${escapeHtml(message.user.name)}</span>
                            <span class="text-xs text-gray-400">${time}</span>
                        </div>
                        <div class="message-bubble received">
                            ${escapeHtml(message.message)}
                        </div>
                    </div>
                </div>
            `;
        }

        container.append(html);
        scrollToBottom();
    }

    function sendMessage() {
        if (!currentConversationId) {
            alert('Please select a conversation first');
            return;
        }

        const input = $('#message-input');
        const message = input.val().trim();

        if (message === '') return;

        $.ajax({
            url: `${API_BASE}/conversations/${currentConversationId}/messages`,
            method: 'POST',
            data: { message: message },
            success: function (data) {
                appendMessage(data);
                input.val('');
            },
            error: function (error) {
                console.error('Error sending message:', error);
                alert('Failed to send message');
            }
        });
    }

    function subscribeToConversation(conversationId) {
        if (window.Echo) {
            // Leave previous channel
            if (window.currentChannel) {
                window.Echo.leave(window.currentChannel);
            }

            // Subscribe to new channel
            const channelName = `conversation.${conversationId}`;
            window.currentChannel = channelName;

            window.Echo.private(channelName)
                .listen('MessageSent', (e) => {
                    console.log('New message received:', e);
                    appendMessage(e);
                })
                .listen('UserTyping', (e) => {
                    console.log('User typing:', e);
                    showTypingIndicator(e.user_name);
                });
        }
    }

    function showTypingIndicator(userName) {
        // Implement typing indicator logic
        console.log(`${userName} is typing...`);
    }

    function loadUsers() {
        $.get(`${API_BASE}/users`, function (data) {
            users = data;
        }).fail(function (error) {
            console.error('Error loading users:', error);
        });
    }

    function renderUsersForNewChat() {
        const container = $('#users-list');
        container.empty();

        users.forEach(user => {
            const avatar = `https://ui-avatars.com/api/?name=${user.name}&background=724FF9&color=fff`;
            const html = `
                <div class="user-item" data-user-id="${user.id}">
                    <img src="${avatar}" alt="${user.name}" class="w-12 h-12 rounded-full">
                    <div class="flex-1">
                        <h5 class="font-semibold text-gray-900">${escapeHtml(user.name)}</h5>
                        <p class="text-xs text-gray-500">${escapeHtml(user.email)}</p>
                    </div>
                    <i data-lucide="message-circle" class="w-5 h-5 text-gray-400"></i>
                </div>
            `;
            container.append(html);
        });

        lucide.createIcons();
        bindUserClick();
    }

    function bindUserClick() {
        $('.user-item').off('click').on('click', function () {
            const userId = $(this).data('user-id');
            createP2PConversation(userId);
        });
    }

    function createP2PConversation(userId) {
        $.ajax({
            url: `${API_BASE}/conversations`,
            method: 'POST',
            data: {
                type: 'p2p',
                user_ids: [userId]
            },
            success: function (conversation) {
                $('#new-chat-modal').fadeOut(200);
                loadConversations();
                setTimeout(() => selectConversation(conversation.id), 500);
            },
            error: function (error) {
                console.error('Error creating conversation:', error);
                alert('Failed to create conversation');
            }
        });
    }

    function renderUsersForGroup() {
        const container = $('#group-users-list');
        container.empty();

        users.forEach(user => {
            const avatar = `https://ui-avatars.com/api/?name=${user.name}&background=724FF9&color=fff`;
            const html = `
                <label class="user-select-item">
                    <input type="checkbox" class="user-checkbox" value="${user.id}" data-name="${user.name}">
                    <img src="${avatar}" alt="${user.name}" class="w-10 h-10 rounded-full">
                    <span class="flex-1 text-sm font-medium text-gray-900">${escapeHtml(user.name)}</span>
                    <i data-lucide="check" class="w-5 h-5 text-secondary checkmark"></i>
                </label>
            `;
            container.append(html);
        });

        lucide.createIcons();
        bindCheckboxChange();
    }

    function bindCheckboxChange() {
        $('.user-checkbox').off('change').on('change', function () {
            updateSelectedCount();
        });
    }

    function updateSelectedCount() {
        const count = $('.user-checkbox:checked').length;
        $('#selected-count').text(count);
    }

    function createGroup() {
        const groupName = $('#group-name-input').val().trim();
        const selectedUserIds = [];

        $('.user-checkbox:checked').each(function () {
            selectedUserIds.push(parseInt($(this).val()));
        });

        if (!groupName) {
            alert('Please enter a group name');
            return;
        }

        if (selectedUserIds.length < 2) {
            alert('Please select at least 2 members');
            return;
        }

        $.ajax({
            url: `${API_BASE}/conversations`,
            method: 'POST',
            data: {
                type: 'group',
                name: groupName,
                user_ids: selectedUserIds
            },
            success: function (conversation) {
                $('#create-group-modal').fadeOut(200);
                $('#group-name-input').val('');
                $('.user-checkbox').prop('checked', false);
                updateSelectedCount();
                loadConversations();
                setTimeout(() => selectConversation(conversation.id), 500);
            },
            error: function (error) {
                console.error('Error creating group:', error);
                alert('Failed to create group');
            }
        });
    }

    // Event Listeners
    function setupEventListeners() {
        // Send message
        $('#send-btn').on('click', sendMessage);
        $('#message-input').on('keypress', function (e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Typing indicator
        $('#message-input').on('input', function () {
            if (!currentConversationId) return;

            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                $.post(`${API_BASE}/conversations/${currentConversationId}/typing`);
            }, 300);
        });

        // Modals
        $('#new-chat-btn').on('click', function () {
            renderUsersForNewChat();
            $('#new-chat-modal').fadeIn(200);
        });

        $('#create-group-btn').on('click', function () {
            renderUsersForGroup();
            $('#create-group-modal').fadeIn(200);
        });

        $('.modal-close').on('click', function () {
            $(this).closest('.modal-overlay').fadeOut(200);
        });

        $('.modal-overlay').on('click', function (e) {
            if ($(e.target).hasClass('modal-overlay')) {
                $(this).fadeOut(200);
            }
        });

        $('#create-group-submit').on('click', createGroup);

        // Search
        $('#search-conversations').on('input', function () {
            const searchTerm = $(this).val().toLowerCase();
            $('.chat-item').each(function () {
                const name = $(this).find('h5').text().toLowerCase();
                const message = $(this).find('p').text().toLowerCase();
                if (name.includes(searchTerm) || message.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        $('#user-search-input').on('input', function () {
            const searchTerm = $(this).val().toLowerCase();
            $('.user-item').each(function () {
                const name = $(this).find('h5').text().toLowerCase();
                if (name.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Emoji button
        $('#emoji-btn').on('click', function () {
            const emojis = ['😊', '😂', '❤️', '👍', '🎉', '🔥', '✨', '💯'];
            const randomEmoji = emojis[Math.floor(Math.random() * emojis.length)];
            const input = $('#message-input');
            input.val(input.val() + randomEmoji);
            input.focus();
        });
    }

    // Utility functions
    function formatTime(date) {
        let hours = date.getHours();
        let minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';

        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;

        return `${hours}:${minutes} ${ampm}`;
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function scrollToBottom() {
        const container = $('#messages-container');
        container.animate({
            scrollTop: container[0].scrollHeight
        }, 300);
    }

    console.log('Chat application initialized');
});
