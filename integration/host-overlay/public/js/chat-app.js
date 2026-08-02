/**
 * Laravel Chat Application
 * Main chat functionality with real-time messaging, file sharing, and presence tracking
 */

// Configuration
const CHAT_CONFIG = {
    API_BASE: '/api',
    PUSHER_KEY: null, // Will be set from blade template
    PUSHER_CLUSTER: null, // Will be set from blade template
    MAX_FILE_SIZE: 10 * 1024 * 1024, // 10MB
};

// Application State
const ChatApp = {
    currentConversationId: null,
    currentUser: null,
    conversations: [],
    users: [],
    onlineUsers: new Set(),
    typingTimer: null,
    selectedFile: null,
    emojiPickerInstance: null,
    categorizedFiles: {},
    notificationsMuted: false,
};

// Track window focus for notifications (using Page Visibility API for tab switching)
let isWindowActive = !document.hidden;

// Listen for visibility changes (tab switching)
document.addEventListener('visibilitychange', function () {
    isWindowActive = !document.hidden;
});

// Also listen for window focus/blur (for window switching)
$(window).on('focus', function () {
    isWindowActive = true;
});

$(window).on('blur', function () {
    isWindowActive = false;
});

/**
 * Initialize the chat application
 */
function initChatApp(user, pusherKey, pusherCluster) {
    ChatApp.currentUser = user;
    ChatApp.notificationsMuted = user.notifications_muted || false;

    CHAT_CONFIG.PUSHER_KEY = pusherKey;
    CHAT_CONFIG.PUSHER_CLUSTER = pusherCluster;

    setupAxios();
    loadConversations();
    loadUsers();
    setupEventListeners();
    initializePusher();


}

/**
 * Setup AJAX defaults
 */
function setupAxios() {
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        }
    });
}

/**
 * Initialize Pusher for real-time communication
 */
function initializePusher() {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: CHAT_CONFIG.PUSHER_KEY,
        cluster: CHAT_CONFIG.PUSHER_CLUSTER,
        forceTLS: true,
        encrypted: true,
        authEndpoint: '/broadcasting/auth'
    });

    // Subscribe to presence channel for online status
    window.Echo.join('online')
        .here((users) => {
            users.forEach(user => ChatApp.onlineUsers.add(user.id));
            updateAllUserStatuses();
        })
        .joining((user) => {
            ChatApp.onlineUsers.add(user.id);
            updateUserStatus(user.id, true);
        })
        .leaving((user) => {
            ChatApp.onlineUsers.delete(user.id);
            updateUserStatus(user.id, false);
        });

    // Subscribe to private user channel for new conversations
    if (ChatApp.currentUser && ChatApp.currentUser.id) {
        window.Echo.private(`App.Models.User.${ChatApp.currentUser.id}`)
            .listen('ConversationCreated', (e) => {
                // Check if conversation implies duplicates
                if (ChatApp.conversations.find(c => c.id === e.id)) {
                    return;
                }

                // Format conversation for frontend
                const conversation = {
                    ...e,
                    unread_count: 0
                };

                // Find other participant for P2P
                if (conversation.type === 'p2p') {
                    const otherUser = conversation.users.find(u => u.id !== ChatApp.currentUser.id);
                    if (otherUser) {
                        conversation.other_participant = {
                            id: otherUser.id,
                            name: otherUser.name,
                            avatar: otherUser.avatar,
                            is_online: ChatApp.onlineUsers.has(otherUser.id)
                        };
                    }
                }

                // Add to list and render
                ChatApp.conversations.unshift(conversation);
                renderConversations(ChatApp.conversations);

                // Subscribe to the new conversation channel
                subscribeToConversation(conversation.id);

                // Play notification sound
                playNotificationSound();

                // Show floating notification
                const sender = conversation.other_participant ? conversation.other_participant.name : 'New Group';
                // showAlert(`New conversation from ${sender}`, 'Info', 'message-square');
            });
    }
}

/**
 * Play notification sound for incoming messages
 */
let notificationAudio = null;
let audioUnlocked = false;

// Initialize audio and request browser notification permission
function initializeNotificationAudio() {
    if (!notificationAudio) {
        notificationAudio = new Audio('/notification-music.mp3');
        notificationAudio.volume = 0.5; // Set volume to 50%
        notificationAudio.load();
    }

    // Request browser notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Unlock audio by playing it silently (browser autoplay policy workaround)
    if (!audioUnlocked && notificationAudio) {
        const originalVolume = notificationAudio.volume;
        notificationAudio.volume = 0; // Mute
        notificationAudio.play()
            .then(() => {
                notificationAudio.pause();
                notificationAudio.currentTime = 0;
                notificationAudio.volume = originalVolume; // Restore volume
                audioUnlocked = true;
            })
            .catch(e => {
                notificationAudio.volume = originalVolume;
            });
    }
}

// Initialize on first user interaction
$(document).one('click', function () {
    initializeNotificationAudio();
});

function playNotificationSound() {
    // Initialize audio if not already done
    if (!notificationAudio) {
        initializeNotificationAudio();
    }

    // Check if notifications are muted in settings
    if (ChatApp.notificationsMuted) {
        return;
    }

    // Play sound
    if (notificationAudio) {
        notificationAudio.currentTime = 0; // Reset to start
        notificationAudio.play()
            .catch(e => {
                // Fallback: try creating new audio instance
                const audio = new Audio('/notification-music.mp3');
                audio.play().catch(err => { });
            });
    }
}

/**
 * Update online status for a specific user
 */
function updateUserStatus(userId, isOnline) {
    const statusColor = isOnline ? 'bg-green-500' : 'bg-gray-400';
    const indicatorHtml = `<span class="absolute bottom-0 right-0 w-3 h-3 ${statusColor} border-2 border-white rounded-full online-status-indicator"></span>`;

    // Update in conversation list
    $(`.chat-item[data-conversation-id]`).each(function () {
        const convId = $(this).data('conversation-id');
        const conv = ChatApp.conversations.find(c => c.id === convId);
        if (conv && conv.type === 'p2p' && conv.other_participant && conv.other_participant.id === userId) {
            // Update the status circle in the list
            const circle = $(this).find('.w-3.h-3.rounded-full');
            circle.removeClass('bg-green-500 bg-gray-400')
                .addClass(isOnline ? 'bg-green-500' : 'bg-gray-400');

            // Also update the avatar overlay if it exists (from my previous edit)
            $(this).find('.relative').find('.absolute').removeClass('bg-green-500 bg-gray-400').addClass(isOnline ? 'bg-green-500' : 'bg-gray-400');
        }
    });

    // Update in user lists
    const modalIndicatorHtml = `<span class="absolute bottom-0 right-0 w-2.5 h-2.5 ${statusColor} border-2 border-white rounded-full online-status-indicator"></span>`;
    $(`.user-item[data-user-id="${userId}"] .relative, .user-select-item input[value="${userId}"] ~ .relative`).each(function () {
        $(this).find('.online-status-indicator').remove();
        $(this).append(modalIndicatorHtml);
    });

    // Update Chat Header if this user is the current chat participant
    if (ChatApp.currentConversationId) {
        const currentConv = ChatApp.conversations.find(c => c.id === ChatApp.currentConversationId);
        if (currentConv && currentConv.type === 'p2p' && currentConv.other_participant && currentConv.other_participant.id === userId) {
            // Re-render the header to show updated status
            selectConversation(ChatApp.currentConversationId);
        }
    }

    // Update online users list
    renderOnlineUsers();
}

/**
 * Update all user statuses
 */
function updateAllUserStatuses() {
    if (ChatApp.conversations.length > 0) {
        renderConversations(ChatApp.conversations);
    }
    renderOnlineUsers();
}

/**
 * Render online users list
 */
function renderOnlineUsers() {
    const container = $('#online-users-list');
    container.empty();

    // Get online users (excluding current user)
    const onlineUsers = ChatApp.users.filter(user =>
        ChatApp.onlineUsers.has(user.id) && user.id !== ChatApp.currentUser.id
    );

    if (onlineUsers.length === 0) {
        return;
    }

    onlineUsers.forEach(user => {
        const avatar = user.avatar
            ? `/storage/${user.avatar}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=724FF9&color=fff`;

        const userHtml = `
            <div class="online-user-item flex-shrink-0 cursor-pointer group" data-user-id="${user.id}" onclick="startChatWithUser(${user.id})">
                <div class="flex flex-col items-center space-y-1">
                    <div class="relative">
                        <img src="${avatar}" alt="${escapeHtml(user.name)}"
                             class="w-12 h-12 rounded-full object-cover ring-2 ring-transparent group-hover:ring-secondary transition">
                        <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
                    </div>
                    <span class="text-xs text-gray-600">${escapeHtml(user.name)}</span>
                </div>
            </div>
        `;

        container.append(userHtml);
    });
}

/**
 * Start chat with a user from online users list
 */
function startChatWithUser(userId) {
    // Check if conversation already exists
    const existingConv = ChatApp.conversations.find(conv =>
        conv.type === 'p2p' && conv.other_participant && conv.other_participant.id === userId
    );

    if (existingConv) {
        // Open existing conversation
        selectConversation(existingConv.id);
        return;
    }

    // Create new conversation
    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations`,
        method: 'POST',
        data: {
            type: 'p2p',
            user_ids: [userId]
        },
        success: function (conversation) {
            // Add to conversations list
            ChatApp.conversations.unshift(conversation);
            renderConversations(ChatApp.conversations);

            // Select the new conversation
            selectConversation(conversation.id);
        },
        error: function (error) {
            console.error('Error creating conversation:', error);
            showAlert('Failed to start conversation. Please try again.', 'Error', 'alert-triangle');
        }
    });
}

/**
 * Load conversations from API
 */
function loadConversations() {
    $.get(`${CHAT_CONFIG.API_BASE}/conversations`, function (data) {
        // Sort conversations by last message time (most recent first)
        data.sort((a, b) => {
            const timeA = a.latest_message ? new Date(a.latest_message.created_at).getTime() : 0;
            const timeB = b.latest_message ? new Date(b.latest_message.created_at).getTime() : 0;
            return timeB - timeA;
        });

        ChatApp.conversations = data;
        renderConversations(data);
        updateNotificationBadge();

        // Subscribe to all conversations for real-time updates
        subscribeToAllConversations(data);
    }).fail(function (error) {
        console.error('Error loading conversations:', error);
    });
}

/**
 * Render conversations list
 */
function renderConversations(convs) {
    const container = $('#conversations-list');
    container.empty();

    if (convs.length === 0) {
        container.html('<div class="text-center text-gray-500 p-4 text-sm">No conversations yet</div>');
        return;
    }

    convs.forEach(conv => {
        const isP2P = conv.type === 'p2p';
        const otherUser = isP2P && conv.other_participant ? conv.other_participant : null;
        const displayName = isP2P && otherUser ? otherUser.name : (conv.name || 'Group Chat');
        const userIsOnline = isP2P && otherUser ? otherUser.is_online : false;

        // Avatar
        const avatar = isP2P && otherUser && otherUser.avatar
            ? `/storage/${otherUser.avatar}`
            : (conv.avatar
                ? `/storage/${conv.avatar}`
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=724FF9&color=fff`);

        const lastMessage = conv.latest_message;
        const lastMessageText = lastMessage
            ? (lastMessage.attachment_path ? 'Sent an attachment' : lastMessage.message)
            : 'No messages yet';
        const lastMessageTime = lastMessage ? formatTime(new Date(lastMessage.created_at)) : '';

        const unreadBadge = conv.unread_count > 0
            ? `<span class="unread-badge bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center">${conv.unread_count}</span>`
            : '';

        // Status indicator circle overlay on avatar (green for online, gray for offline)
        const statusIndicator = isP2P
            ? `<div class="absolute bottom-0 right-0 w-3 h-3 rounded-full ${userIsOnline ? 'bg-green-500' : 'bg-gray-400'} border-2 border-white"></div>`
            : '';

        const html = `
            <div class="chat-item p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-100 transition"
                data-conversation-id="${conv.id}"
                onclick="selectConversation(${conv.id})">
                <div class="flex items-center space-x-3">
                    <div class="relative flex-shrink-0">
                        <img src="${avatar}" alt="${escapeHtml(displayName)}" class="w-12 h-12 rounded-full object-cover">
                        ${statusIndicator}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <h3 class="font-semibold text-gray-900 truncate">${escapeHtml(displayName)}</h3>
                            ${unreadBadge}
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-500 truncate overflow-hidden whitespace-nowrap text-ellipsis max-w-72 flex-1 min-w-0">
                                ${escapeHtml(lastMessageText)}
                            </p>
                            <span class="last-message-time text-xs text-gray-400 ml-2 flex-shrink-0">${lastMessageTime}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.append(html);
    });
}

/**
 * Bind click events to conversations
 */
function bindConversationClick() {
    $('.chat-item').off('click').on('click', function () {
        const conversationId = $(this).data('conversation-id');
        selectConversation(conversationId);
    });
}

/**
 * Select and open a conversation
 */
function selectConversation(conversationId) {
    ChatApp.currentConversationId = conversationId;

    $('.chat-item').removeClass('active');
    $(`.chat-item[data-conversation-id="${conversationId}"]`).addClass('active');

    loadMessages(conversationId);
    subscribeToConversation(conversationId);

    // Mark as read
    $.post(`${CHAT_CONFIG.API_BASE}/conversations/${conversationId}/read`, function () {
        const chatItem = $(`.chat-item[data-conversation-id="${conversationId}"]`);
        chatItem.find('.unread-badge').remove();

        const conv = ChatApp.conversations.find(c => c.id === conversationId);
        if (conv) {
            conv.unread_count = 0;
            updateNotificationBadge();
        }
    });

    const conversation = ChatApp.conversations.find(c => c.id === conversationId);
    if (conversation) {
        const isP2P = conversation.type === 'p2p';
        const otherUser = isP2P && conversation.other_participant ? conversation.other_participant : null;
        const displayName = conversation.name || (isP2P && otherUser ? otherUser.name : 'Group Chat');

        let headerHtml = '';

        if (isP2P && otherUser) {
            const avatar = otherUser.avatar
                ? `/storage/${otherUser.avatar}`
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(otherUser.name)}&background=724FF9&color=fff`;

            const isOnline = ChatApp.onlineUsers.has(otherUser.id);
            const statusText = isOnline ? 'Online' : 'Offline';
            const statusColor = isOnline ? 'text-green-500' : 'text-gray-400';

            headerHtml = `
                <div class="relative">
                    <img src="${avatar}" alt="${escapeHtml(displayName)}" class="w-10 h-10 rounded-full object-cover">
                    <div class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full ${isOnline ? 'bg-green-500' : 'bg-gray-400'} border-2 border-white"></div>
                </div>
                <div class="flex flex-col">
                    <h2 class="text-xs md:text-base lg:text-lg font-semibold text-gray-900 leading-tight" id="chat-header-title">${escapeHtml(displayName)}</h2>
                    <span class="text-xs ${statusColor} font-medium">${statusText}</span>
                </div>
            `;
        } else {
            // Group chat or fallback
            const avatar = conversation.avatar || 'https://ui-avatars.com/api/?name=Group&background=8b5cf6&color=fff';
            headerHtml = `
                <div class="relative">
                    <img src="${avatar}" alt="${escapeHtml(displayName)}" class="w-10 h-10 rounded-full object-cover">
                </div>
                <div class="flex flex-col">
                    <h2 class="text-lg font-semibold text-gray-900 leading-tight" id="chat-header-title">${escapeHtml(displayName)}</h2>
                    <span class="text-xs text-gray-500 font-medium">${conversation.participants ? conversation.participants.length + ' members' : 'Group Chat'}</span>
                </div>
            `;
        }

        $('#chat-header-content').html(headerHtml);

        // Show/hide AI buttons (hide for AI assistant conversations)
        if (conversation.type === 'ai_assistant') {
            $('#ai-summary-btn').addClass('hidden');
            $('#ai-suggestions-btn').addClass('hidden');
        } else {
            $('#ai-summary-btn').removeClass('hidden');
            $('#ai-suggestions-btn').removeClass('hidden');
        }

        updateChatInfo(conversation);
    }
}

/**
 * Update chat info sidebar
 */
function updateChatInfo(conversation) {
    const container = $('#chat-info-content');
    container.empty();

    if (conversation.type === 'p2p' && conversation.other_participant) {
        // P2P Chat Info
        const participant = conversation.other_participant;
        const avatar = participant.avatar
            ? `/storage/${participant.avatar}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(participant.name)}&background=724FF9&color=fff`;

        const isOnline = ChatApp.onlineUsers.has(participant.id);
        const statusColor = isOnline ? 'bg-green-500' : 'bg-gray-400';
        const statusText = isOnline ? 'Online' : 'Offline';

        const html = `
            <div class="flex flex-col items-center mb-6 pb-6 border-b border-gray-200">
                <div class="relative mb-3">
                    <img src="${avatar}" alt="${escapeHtml(participant.name)}" class="w-20 h-20 rounded-full object-cover">
                    <span class="absolute bottom-0 right-0 w-5 h-5 ${statusColor} border-2 border-white rounded-full"></span>
                </div>
                <h4 class="text-lg font-semibold text-gray-900">${escapeHtml(participant.name)}</h4>
                <p class="text-sm text-gray-500">${escapeHtml(participant.email)}</p>
                <span class="mt-2 px-3 py-1 text-xs font-medium ${isOnline ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'} rounded-full">
                    ${statusText}
                </span>
            </div>
        `;
        container.append(html);
    } else if (conversation.type === 'group') {
        // Group Chat Info
        const avatar = conversation.avatar || 'https://ui-avatars.com/api/?name=Group&background=8b5cf6&color=fff';
        const participantCount = conversation.participants ? conversation.participants.length : 0;

        const html = `
            <div class="flex flex-col items-center mb-6 pb-6 border-b border-gray-200">
                <div class="relative mb-3">
                    <img src="${avatar}" alt="Group" class="w-20 h-20 rounded-full object-cover">
                </div>
                <h4 class="text-lg font-semibold text-gray-900">${escapeHtml(conversation.name)}</h4>
                <p class="text-sm text-gray-500 mb-4">${participantCount} members</p>

                <div class="flex space-x-2 w-full">
                    <button onclick="openEditGroupModal()" class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-sm font-medium">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                        <span>Edit</span>
                    </button>
                    <button onclick="openAddMemberModal()" class="flex-1 flex items-center justify-center space-x-2 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition text-sm font-medium">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        <span>Add</span>
                    </button>
                </div>
            </div>

            <div class="mb-6">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">Group Members</h5>
                <div id="group-members-list" class="space-y-2 max-h-60 overflow-y-auto">
                    <!-- Members will be added here -->
                </div>
            </div>

            <div class="mb-6 pb-6 border-b border-gray-200">
                <button onclick="leaveGroup()" class="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition text-sm font-medium">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span>Leave Group</span>
                </button>
            </div>
        `;
        container.append(html);

        // Add group members
        if (conversation.participants && conversation.participants.length > 0) {
            const membersList = $('#group-members-list');
            conversation.participants.forEach(member => {
                const memberAvatar = member.avatar
                    ? `/storage/${member.avatar}`
                    : `https://ui-avatars.com/api/?name=${encodeURIComponent(member.name)}&background=724FF9&color=fff`;

                const isOnline = ChatApp.onlineUsers.has(member.id);
                const statusColor = isOnline ? 'bg-green-500' : 'bg-gray-400';
                const isCurrentUser = member.id === ChatApp.currentUser.id;

                const removeBtn = !isCurrentUser
                    ? `<button onclick="removeMemberFromGroup(${member.id})" class="p-1 text-gray-400 hover:text-red-500 transition opacity-0 group-hover:opacity-100" title="Remove Member">
                           <i data-lucide="user-minus" class="w-4 h-4"></i>
                       </button>`
                    : '';

                const memberHtml = `
                    <div class="group flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition">
                        <div class="relative">
                            <img src="${memberAvatar}" alt="${escapeHtml(member.name)}"
                                 class="w-10 h-10 rounded-full object-cover">
                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 ${statusColor} border-2 border-white rounded-full"></span>
                        </div>
                        <div class="flex-1 min-w-0 justify-items-start">
                            <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(member.name)}</p>
                            <p class="text-xs text-gray-500 truncate">${escapeHtml(member.email)}</p>
                        </div>
                        ${removeBtn}
                    </div>
                `;
                membersList.append(memberHtml);
            });
        }
    }

    // Add summary cards and file types
    container.append(`
        <div id="summary-cards" class="grid grid-cols-2 gap-3 mb-6 pb-6 border-b border-gray-200">
            <!-- Will be populated after loading content -->
        </div>

        <div class="mb-6 pb-6 border-b border-gray-200">
            <h5 class="text-sm font-semibold text-gray-700 mb-3">File Types</h5>
            <div id="file-types-list" class="space-y-2">
                <!-- File types will be added here -->
            </div>
        </div>

        <div class="space-y-2">
            <button onclick="confirmDeleteChat()" class="w-full flex items-center justify-center space-x-2 px-4 py-3 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span class="font-medium">Delete Chat</span>
            </button>
        </div>
    `);

    lucide.createIcons();
    loadSharedContent(conversation.id);
}

/**
 * Load shared content (files, links) for a conversation
 */
function loadSharedContent(conversationId) {
    $.get(`${CHAT_CONFIG.API_BASE}/conversations/${conversationId}/messages`, function (messages) {
        const mediaItems = [];
        const fileItems = [];
        const linkItems = [];

        const documents = [];
        const photos = [];
        const movies = [];
        const others = [];

        messages.forEach(msg => {
            if (msg.attachment_path) {
                const fileUrl = `/storage/${msg.attachment_path}`;
                const fileType = msg.attachment_type || '';
                const fileSize = msg.attachment_size || 0;

                const fileData = {
                    url: fileUrl,
                    name: msg.attachment_name,
                    size: fileSize,
                    type: fileType,
                    date: msg.created_at
                };

                if (fileType.startsWith('image/')) {
                    photos.push(fileData);
                    mediaItems.push(fileData);
                } else if (fileType.startsWith('video/')) {
                    movies.push(fileData);
                    fileItems.push(fileData);
                } else if (fileType.includes('document') || fileType.includes('pdf') ||
                    fileType.includes('word') || fileType.includes('text')) {
                    documents.push(fileData);
                    fileItems.push(fileData);
                } else {
                    others.push(fileData);
                    fileItems.push(fileData);
                }
            }

            if (msg.message) {
                const urlRegex = /(https?:\/\/[^\s]+)/g;
                const urls = msg.message.match(urlRegex);
                if (urls) {
                    urls.forEach(url => {
                        linkItems.push({
                            url: url,
                            message: msg.message,
                            user: msg.user.name,
                            date: msg.created_at
                        });
                    });
                }
            }
        });

        const totalFiles = fileItems.length;
        const totalPhotos = photos.length;

        // Populate summary cards
        const summaryCards = $('#summary-cards');
        summaryCards.html(`
            <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-teal-200 rounded-full -mr-8 -mt-8 opacity-50"></div>
                <div class="relative">
                     <i data-lucide="folder" class="lucide lucide-folder w-6 h-6 text-teal-600 mb-2"></i>
                    <div class="text-2xl font-bold text-teal-900">${totalFiles}</div>
                    <div class="text-xs text-teal-700">All files</div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-purple-200 rounded-full -mr-8 -mt-8 opacity-50"></div>
                <div class="relative">
                     <i data-lucide="image" class="lucide lucide-image w-6 h-6 text-purple-600 mb-2"></i>
                    <div class="text-2xl font-bold text-purple-900">${totalPhotos}</div>
                    <div class="text-xs text-purple-700">Photos</div>
                </div>
            </div>
        `);

        // Populate file types list
        const fileTypesList = $('#file-types-list');
        fileTypesList.empty();

        const calculateSize = (items) => {
            const bytes = items.reduce((sum, item) => sum + item.size, 0);
            return formatFileSize(bytes);
        };

        if (documents.length > 0 || totalFiles === 0) {
            fileTypesList.append(`
                <div class="file-type-item" onclick="showFileCategory('documents')">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="file-text" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                        <h5 class="text-sm font-semibold text-gray-900">Documents</h5>
                        <p class="text-xs text-gray-500">${documents.length} files, ${calculateSize(documents)}</p>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                </div>
            `);
        }

        if (photos.length > 0 || totalFiles === 0) {
            fileTypesList.append(`
                <div class="file-type-item" onclick="showFileCategory('photos')">
                    <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="image" class="w-5 h-5 text-yellow-600"></i>
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                        <h5 class="text-sm font-semibold text-gray-900">Photos</h5>
                        <p class="text-xs text-gray-500">${photos.length} files, ${calculateSize(photos)}</p>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                </div>
            `);
        }

        if (movies.length > 0 || totalFiles === 0) {
            fileTypesList.append(`
                <div class="file-type-item" onclick="showFileCategory('movies')">
                    <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="film" class="w-5 h-5 text-teal-600"></i>
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                        <h5 class="text-sm font-semibold text-gray-900">Movies</h5>
                        <p class="text-xs text-gray-500">${movies.length} files, ${calculateSize(movies)}</p>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                </div>
            `);
        }

        if (others.length > 0 || totalFiles === 0) {
            fileTypesList.append(`
                <div class="file-type-item" onclick="showFileCategory('other')">
                    <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="file" class="w-5 h-5 text-red-600"></i>
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                        <h5 class="text-sm font-semibold text-gray-900">Other</h5>
                        <p class="text-xs text-gray-500">${others.length} files, ${calculateSize(others)}</p>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                </div>
            `);
        }

        lucide.createIcons();

        ChatApp.categorizedFiles = {
            documents: documents,
            photos: photos,
            movies: movies,
            other: others
        };
        window.categorizedFiles = ChatApp.categorizedFiles;
    });
}

// Continue in next part...
/**
 * Laravel Chat Application - Part 2
 * Messages, file handling, modals, and utility functions
 */

/**
 * Show file category modal
 */
function showFileCategory(category) {


    if (!ChatApp.categorizedFiles || !ChatApp.categorizedFiles[category]) {
        showAlert('No files found in this category');
        return;
    }

    const files = ChatApp.categorizedFiles[category];
    const categoryNames = {
        documents: 'Documents',
        photos: 'Photos',
        movies: 'Movies',
        other: 'Other Files'
    };

    $('#file-category-title').text(categoryNames[category] || 'Files');

    const container = $('#file-category-content');
    container.empty();

    if (files.length === 0) {
        container.html('<div class="text-center text-gray-500 py-8">No files in this category</div>');
    } else {
        const grid = $('<div class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>');

        files.forEach(file => {
            const isImage = file.type.startsWith('image/');
            const date = new Date(file.date);
            const formattedDate = date.toLocaleDateString() + ' ' + formatTime(date);

            let fileHtml;
            if (isImage) {
                fileHtml = `
                    <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition cursor-pointer" onclick="showImagePreview('${file.url}', '${escapeHtml(file.name)}')">
                        <img src="${file.url}" alt="${escapeHtml(file.name)}" class="w-full h-48 object-cover">
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(file.name)}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-500">${formatFileSize(file.size)}</span>
                                <a href="${file.url}" download="${escapeHtml(file.name)}" class="text-xs text-primary hover:text-secondary flex items-center space-x-1" onclick="event.stopPropagation()">
                                    <i data-lucide="download" class="w-3 h-3"></i>
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                const iconMap = {
                    'application/pdf': 'file-text',
                    'application/msword': 'file-text',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'file-text',
                    'video/': 'film',
                    'audio/': 'music'
                };

                let icon = 'file';
                for (const [type, iconName] of Object.entries(iconMap)) {
                    if (file.type.includes(type)) {
                        icon = iconName;
                        break;
                    }
                }

                fileHtml = `
                    <a href="${file.url}" download="${escapeHtml(file.name)}" class="flex items-center space-x-3 p-4 border border-gray-200 rounded-lg hover:bg-gray-50 hover:shadow-md transition">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="${icon}" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(file.name)}</p>
                            <p class="text-xs text-gray-500">${formatFileSize(file.size)} • ${formattedDate}</p>
                        </div>
                        <i data-lucide="download" class="w-5 h-5 text-gray-400"></i>
                    </a>
                `;
            }

            grid.append(fileHtml);
        });

        container.append(grid);
    }

    lucide.createIcons();
    $('#file-category-modal').fadeIn(200);
}

/**
 * Confirm delete chat
 */
function confirmDeleteChat() {
    if (!ChatApp.currentConversationId) return;

    showConfirmationModal({
        title: 'Delete Conversation',
        message: 'Are you sure you want to delete this conversation? This action cannot be undone.',
        icon: 'alert-triangle',
        confirmText: 'Delete',
        confirmColor: 'bg-red-600 text-white hover:bg-red-700',
        onConfirm: deleteConversation
    });
}

/**
 * Delete conversation
 */
function deleteConversation() {
    if (!ChatApp.currentConversationId) {
        return;
    }

    const conversationId = ChatApp.currentConversationId;

    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations/${conversationId}`,
        method: 'DELETE',
        success: function (response) {


            // Close the modal
            $('#delete-chat-modal').fadeOut(200);

            // Remove conversation from the list
            $(`.chat-item[data-conversation-id="${conversationId}"]`).remove();

            // Remove from conversations array
            ChatApp.conversations = ChatApp.conversations.filter(c => c.id !== conversationId);

            // Clear current conversation
            ChatApp.currentConversationId = null;

            // Clear messages container
            $('#messages-container').html(`
                <div class="text-center text-gray-500 mt-20">
                    <i data-lucide="message-circle" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                    <p>Select a conversation to start chatting</p>
                </div>
            `);

            // Clear chat header
            $('#chat-header-title').text('Select a conversation');

            // Clear chat info sidebar
            $('#chat-info-content').html('<div class="text-center text-gray-500">Select a conversation to view details</div>');

            lucide.createIcons();

            // Show success message (optional)

        },
        error: function (error) {
            console.error('Error deleting conversation:', error);
            $('#delete-chat-modal').fadeOut(200);

            if (error.status === 403) {
                showAlert('You do not have permission to delete this conversation', 'Error', 'alert-triangle');
            } else {
                showAlert('Failed to delete conversation. Please try again.', 'Error', 'alert-triangle');
            }
        }
    });
}

/**
 * Show image preview in modal
 */
function showImagePreview(imageUrl, imageName) {
    $('#preview-image').attr('src', imageUrl);
    $('#preview-image-name').text(imageName || 'Image');
    $('#image-download-btn').attr('href', imageUrl).attr('download', imageName || 'image');
    $('#image-preview-modal').fadeIn(200);
    lucide.createIcons();
}

/**
 * Load messages for a conversation
 */
function loadMessages(conversationId) {
    $.get(`${CHAT_CONFIG.API_BASE}/conversations/${conversationId}/messages`, function (messages) {
        renderMessages(messages);
    }).fail(function (error) {
        console.error('Error loading messages:', error);
    });
}

/**
 * Render messages in the chat area
 */
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

/**
 * Append a message to the chat
 */
function appendMessage(message) {
    const container = $('#messages-container');
    const isSent = message.user_id === ChatApp.currentUser.id;
    const time = formatTime(new Date(message.created_at));

    const avatar = message.user.avatar
        ? `/storage/${message.user.avatar}`
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(message.user.name)}&background=724FF9&color=fff`;

    let attachmentHtml = '';
    if (message.attachment_path) {
        const isImage = message.attachment_type && message.attachment_type.startsWith('image/');
        const fileUrl = `/storage/${message.attachment_path}`;

        if (isImage) {
            attachmentHtml = `
                <div class="mt-2">
                    <img src="${fileUrl}" alt="${escapeHtml(message.attachment_name)}"
                        class="max-w-xs rounded-lg border border-gray-200 cursor-pointer hover:opacity-90 transition"
                        onclick="showImagePreview('${fileUrl}', '${escapeHtml(message.attachment_name)}')">
                </div>
            `;
        } else {
            const fileSize = formatFileSize(message.attachment_size);
            attachmentHtml = `
                <a href="${fileUrl}" download="${escapeHtml(message.attachment_name)}"
                    class="mt-2 flex items-center space-x-2 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    <i data-lucide="file" class="w-8 h-8 text-secondary"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(message.attachment_name)}</p>
                        <p class="text-xs text-gray-500">${fileSize}</p>
                    </div>
                    <i data-lucide="download" class="w-5 h-5 text-gray-400"></i>
                </a>
            `;
        }
    }

    // Read receipt indicator for sent messages
    let readReceiptHtml = '';
    if (isSent && message.read_by && message.read_by.length > 0) {
        // Message has been read
        readReceiptHtml = `
            <div class="flex items-center space-x-1 mt-1">
                <i data-lucide="check-check" class="w-3 h-3 text-blue-500"></i>
                <span class="text-xs text-blue-500">Seen</span>
            </div>
        `;
    } else if (isSent) {
        // Message delivered but not read
        readReceiptHtml = `
            <div class="flex items-center space-x-1 mt-1">
                <i data-lucide="check" class="w-3 h-3 text-gray-400"></i>
                <span class="text-xs text-gray-400">Delivered</span>
            </div>
        `;
    }

    let html;
    if (isSent) {
        // Check if message has only media (no text)
        const hasOnlyMedia = message.attachment_path && (!message.message || message.message.trim() === '');
        const mediaOnlyClass = hasOnlyMedia ? ' media-only' : '';

        html = `
            <div class="flex items-end justify-end space-x-3" data-message-id="${message.id}">
                <div class="flex-1 flex flex-col items-end">
                    <div class="flex items-center space-x-2 mb-1">
                        <span class="text-xs text-gray-400">You, ${time}</span>
                    </div>
                    <div class="message-bubble sent${mediaOnlyClass}">
                        ${attachmentHtml}
                        ${escapeHtml(message.message)}
                    </div>
                    ${readReceiptHtml}
                </div>
            </div>
        `;
    } else {
        // Check if message has only media (no text)
        const hasOnlyMedia = message.attachment_path && (!message.message || message.message.trim() === '');
        const mediaOnlyClass = hasOnlyMedia ? ' media-only' : '';

        html = `
            <div class="flex items-start space-x-3" data-message-id="${message.id}">
                <img src="${avatar}" alt="${message.user.name}" class="w-8 h-8 rounded-full flex-shrink-0 object-cover">
                <div class="flex-1">
                    <div class="flex items-center space-x-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900">${escapeHtml(message.user.name)}</span>
                        <span class="text-xs text-gray-400">${time}</span>
                    </div>
                    <div class="message-bubble inline-block received${mediaOnlyClass}">
                        ${escapeHtml(message.message)}
                        ${attachmentHtml}
                    </div>
                </div>
            </div>
        `;
    }

    container.append(html);
    lucide.createIcons();
    scrollToBottom();

    // Mark received message as read after it's been appended to DOM
    if (!isSent) {
        setTimeout(() => {
            markMessageAsRead(message.id);
        }, 100);
    }

    // Refresh shared content if this message has attachments or links
    if (ChatApp.currentConversationId && (message.attachment_path || (message.message && message.message.match(/(https?:\/\/[^\s]+)/g)))) {
        loadSharedContent(ChatApp.currentConversationId);
    }
}

/**
 * Send a message
 */
function sendMessage() {
    if (!ChatApp.currentConversationId) {
        showAlert('Please select a conversation first');
        return;
    }

    const input = $('#message-input');
    const message = input.val().trim();
    const fileInput = $('#attachment-input')[0];
    const hasFile = fileInput.files.length > 0;

    if (message === '' && !hasFile) return;

    // Check if this is an AI conversation
    if (ChatApp.currentConversationId === 'ai-new' || ChatApp.aiConversationId === ChatApp.currentConversationId) {
        if (message && !hasFile) {
            sendAIMessage(message);
            input.val('');
        }
        return;
    }

    // Create optimistic message object
    const optimisticMessage = {
        id: 'temp-' + Date.now(),
        conversation_id: ChatApp.currentConversationId,
        user_id: ChatApp.currentUser.id,
        message: message,
        created_at: new Date().toISOString(),
        user: ChatApp.currentUser,
        attachment_path: null,
        attachment_name: null,
        attachment_type: null,
        attachment_size: null,
        read_by: []
    };

    // Show message immediately (optimistic UI)
    if (message && !hasFile) {
        appendMessage(optimisticMessage);
        input.val('');
    }

    const formData = new FormData();
    if (message) {
        formData.append('message', message);
    }
    if (hasFile) {
        formData.append('attachment', fileInput.files[0]);
    }

    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations/${ChatApp.currentConversationId}/messages`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (data) {
            // If we showed optimistic message, replace it with real one
            if (message && !hasFile) {
                $(`[data-message-id="${optimisticMessage.id}"]`).remove();
                appendMessage(data);
            } else {
                // For file uploads, append normally
                appendMessage(data);
            }

            input.val('');
            fileInput.value = '';
            $('#file-preview-container').addClass('hidden');
            ChatApp.selectedFile = null;

            // Update chat list with the new message
            const chatItem = $(`.chat-item[data-conversation-id="${ChatApp.currentConversationId}"]`);
            if (chatItem.length > 0) {
                const messageText = data.message || (data.attachment_path ? 'Sent an attachment' : '');
                chatItem.find('p').first().text(messageText);
                chatItem.find('.last-message-time').text(formatTime(new Date(data.created_at)));

                // Move to top of list
                chatItem.parent().prepend(chatItem);
            }
        },
        error: function (error) {
            console.error('Error sending message:', error);

            // Remove optimistic message on error
            if (message && !hasFile) {
                $(`[data-message-id="${optimisticMessage.id}"]`).remove();
            }

            if (error.responseJSON && error.responseJSON.errors) {
                const errors = error.responseJSON.errors;
                if (errors.attachment) {
                    showAlert(errors.attachment[0], 'Validation Error');
                } else {
                    showAlert('Failed to send message', 'Error', 'alert-triangle');
                }
            } else {
                showAlert('Failed to send message', 'Error', 'alert-triangle');
            }
        }
    });
}

/**
 * Subscribe to all conversations for real-time updates
 */
function subscribeToAllConversations(conversations) {
    if (!window.Echo || !conversations) return;

    // Store subscribed channels
    if (!ChatApp.subscribedChannels) {
        ChatApp.subscribedChannels = [];
    }

    conversations.forEach(conversation => {
        const channelName = `conversation.${conversation.id}`;

        // Skip if already subscribed
        if (ChatApp.subscribedChannels.includes(channelName)) {
            return;
        }

        window.Echo.private(channelName)
            .listen('MessageSent', (e) => {
                // Check if conversation exists in the list
                const chatItem = $(`.chat-item[data-conversation-id="${e.conversation_id}"]`);

                if (chatItem.length === 0) {
                    // New conversation - reload the conversations list
                    loadConversations();
                    return;
                }

                // Only update unread count if not currently viewing this conversation
                if (!ChatApp.currentConversationId || parseInt(ChatApp.currentConversationId) !== parseInt(e.conversation_id)) {
                    let badge = chatItem.find('.unread-badge');

                    if (badge.length) {
                        const count = parseInt(badge.text()) + 1;
                        badge.text(count);
                    } else {
                        chatItem.find('.flex-1 .flex.items-center.justify-between.mb-1').append(
                            `<span class="unread-badge bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center">1</span>`
                        );
                    }

                    const conv = ChatApp.conversations.find(c => c.id === e.conversation_id);
                    if (conv) {
                        conv.unread_count = (conv.unread_count || 0) + 1;
                    }

                    // Update last message preview
                    const messageText = e.message || (e.attachment_path ? 'Sent an attachment' : '');
                    chatItem.find('p').first().text(messageText);
                    chatItem.find('.last-message-time').text(formatTime(new Date(e.created_at)));

                    // Move to top of list
                    chatItem.parent().prepend(chatItem);

                    // Update notification badge count
                    updateNotificationBadge();

                    // Play notification sound (only for messages from other users)
                    if (e.user_id !== ChatApp.currentUser.id) {
                        playNotificationSound();
                    }
                }
            });

        ChatApp.subscribedChannels.push(channelName);
    });
}

/**
 * Subscribe to conversation for real-time updates
 */
function subscribeToConversation(conversationId) {
    if (window.Echo) {
        if (window.currentChannel) {
            window.Echo.leave(window.currentChannel);
        }

        const channelName = `conversation.${conversationId}`;
        window.currentChannel = channelName;

        window.Echo.private(channelName)
            .listen('MessageSent', (e) => {


                // If viewing this conversation, append message and mark as read
                if (ChatApp.currentConversationId && parseInt(ChatApp.currentConversationId) === parseInt(e.conversation_id)) {
                    hideTypingIndicator();
                    appendMessage(e);
                    $.post(`${CHAT_CONFIG.API_BASE}/conversations/${e.conversation_id}/read`);
                }

                // Update last message preview
                const chatItem = $(`.chat-item[data-conversation-id="${e.conversation_id}"]`);
                const messageText = e.message || (e.attachment_path ? 'Sent an attachment' : '');
                chatItem.find('p').first().text(messageText);
                chatItem.find('.last-message-time').text(formatTime(new Date(e.created_at)));
                chatItem.parent().prepend(chatItem);

                // Update unread count if not viewing this conversation
                if (!ChatApp.currentConversationId || parseInt(ChatApp.currentConversationId) !== parseInt(e.conversation_id)) {
                    const conv = ChatApp.conversations.find(c => c.id === parseInt(e.conversation_id));
                    if (conv && e.user_id !== ChatApp.currentUser.id) {
                        conv.unread_count = (conv.unread_count || 0) + 1;

                        // Update unread badge in chat item
                        const existingBadge = chatItem.find('.unread-badge');
                        if (existingBadge.length > 0) {
                            existingBadge.text(conv.unread_count);
                        } else {
                            chatItem.find('h3').after(`<span class="unread-badge bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center">${conv.unread_count}</span>`);
                        }

                        // Update notification badge
                        updateNotificationBadge();

                        // Play notification sound (only when not viewing this conversation)
                        playNotificationSound();
                    }
                }
            })
            .listen('UserTyping', (e) => {

                showTypingIndicator(e.user_name);
            })
            .listen('MessageRead', (e) => {
                // Update read status in UI if viewing this conversation
                if (ChatApp.currentConversationId && parseInt(ChatApp.currentConversationId) === parseInt(e.conversationId)) {
                    updateMessageReadStatus(e.messageId, e.userId);
                }
            });
    }
}

/**
 * Show typing indicator
 */
let typingTimeout;

function showTypingIndicator(userName) {
    const indicator = $('#typing-indicator');
    const text = $('#typing-text');

    text.text(`${userName} is typing...`);
    indicator.removeClass('hidden');
    scrollToBottom();

    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        hideTypingIndicator();
    }, 3000);
}

/**
 * Hide typing indicator
 */
function hideTypingIndicator() {
    const indicator = $('#typing-indicator');
    indicator.addClass('hidden');
    clearTimeout(typingTimeout);
}

/**
 * Load users from API
 */
function loadUsers() {
    $.get(`${CHAT_CONFIG.API_BASE}/users`, function (data) {
        ChatApp.users = data;
        renderOnlineUsers();
    }).fail(function (error) {
        console.error('Error loading users:', error);
    });
}

/**
 * Render users for new chat modal
 */
function renderUsersForNewChat() {
    const container = $('#users-list');
    container.empty();

    ChatApp.users.forEach(user => {
        const avatar = user.avatar
            ? `/storage/${user.avatar}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=724FF9&color=fff`;

        const onlineIndicator = ChatApp.onlineUsers.has(user.id)
            ? '<span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full online-status-indicator"></span>'
            : '<span class="absolute bottom-0 right-0 w-3 h-3 bg-gray-400 border-2 border-white rounded-full online-status-indicator"></span>';

        const html = `
            <div class="user-item" data-user-id="${user.id}">
                <div class="relative">
                    <img src="${avatar}" alt="${user.name}" class="w-12 h-12 rounded-full object-cover">
                    ${onlineIndicator}
                </div>
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

/**
 * Bind click events to users
 */
function bindUserClick() {
    $('.user-item').off('click').on('click', function () {
        const userId = $(this).data('user-id');
        createP2PConversation(userId);
    });
}

/**
 * Create P2P conversation
 */
function createP2PConversation(userId) {
    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations`,
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
            showAlert('Failed to create conversation', 'Error', 'alert-triangle');
        }
    });
}

/**
 * Render users for group creation
 */
function renderUsersForGroup() {
    const container = $('#group-users-list');
    container.empty();

    ChatApp.users.forEach(user => {
        const avatar = user.avatar
            ? `/storage/${user.avatar}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=724FF9&color=fff`;

        const onlineIndicator = ChatApp.onlineUsers.has(user.id)
            ? '<span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full online-status-indicator"></span>'
            : '<span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-gray-400 border-2 border-white rounded-full online-status-indicator"></span>';

        const html = `
            <label class="user-select-item">
                <input type="checkbox" class="user-checkbox" value="${user.id}" data-name="${user.name}">
                <div class="relative">
                    <img src="${avatar}" alt="${user.name}" class="w-10 h-10 rounded-full object-cover">
                    ${onlineIndicator}
                </div>
                <span class="flex-1 text-sm font-medium text-gray-900">${escapeHtml(user.name)}</span>
                <i data-lucide="check" class="w-5 h-5 text-secondary checkmark"></i>
            </label>
        `;
        container.append(html);
    });

    lucide.createIcons();
    bindCheckboxChange();
}

/**
 * Bind checkbox change events
 */
function bindCheckboxChange() {
    $('.user-checkbox').off('change').on('change', function () {
        updateSelectedCount();
    });
}

/**
 * Update selected count
 */
function updateSelectedCount() {
    const count = $('.user-checkbox:checked').length;
    $('#selected-count').text(count);
}

/**
 * Create group conversation
 */
function createGroup() {
    const groupName = $('#group-name-input').val().trim();
    const selectedUserIds = [];

    $('.user-checkbox:checked').each(function () {
        selectedUserIds.push(parseInt($(this).val()));
    });

    if (!groupName) {
        showAlert('Please enter a group name', 'Validation Error');
        return;
    }

    if (selectedUserIds.length < 2) {
        showAlert('Please select at least 2 members', 'Validation Error');
        return;
    }

    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations`,
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
            showAlert('Failed to create group', 'Error', 'alert-triangle');
        }
    });
}

/**
 * Setup all event listeners
 */
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
        if (!ChatApp.currentConversationId) return;

        clearTimeout(ChatApp.typingTimer);
        ChatApp.typingTimer = setTimeout(() => {
            $.post(`${CHAT_CONFIG.API_BASE}/conversations/${ChatApp.currentConversationId}/typing`);
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

    // Modal close handlers
    $(document).on('click', '.modal-close', function () {
        const modal = $(this).closest('.modal-overlay');
        if (modal.length > 0) {
            // For modals using fadeIn/fadeOut
            modal.fadeOut(200);
        } else {
            // For AI summary modal using hidden class
            $('#ai-summary-modal').addClass('hidden');
        }
    });

    // Close modal when clicking outside
    $(document).on('click', '.modal-overlay', function (e) {
        if ($(e.target).hasClass('modal-overlay')) {
            $(this).fadeOut(200);
        }
    });

    // Close AI summary modal when clicking outside
    $(document).on('click', '#ai-summary-modal', function (e) {
        if (e.target.id === 'ai-summary-modal') {
            $(this).addClass('hidden');
        }
    });

    $('#create-group-submit').on('click', createGroup);

    // Delete conversation
    $('#confirm-delete-btn').on('click', deleteConversation);

    // Search
    $('#search-conversations').on('input', function () {
        const searchTerm = $(this).val().toLowerCase();
        $('.chat-item').each(function () {
            const name = $(this).find('h3').text().toLowerCase();
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

    // Emoji picker
    initEmojiPicker();

    // File attachment
    $('#attachment-btn').on('click', function () {
        $('#attachment-input').click();
    });

    $('#attachment-input').on('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > CHAT_CONFIG.MAX_FILE_SIZE) {
            showAlert('File size must be less than 10MB', 'Validation Error');
            $(this).val('');
            return;
        }

        ChatApp.selectedFile = file;
        $('#file-preview-name').text(file.name);
        $('#file-preview-size').text(formatFileSize(file.size));
        $('#file-preview-container').removeClass('hidden');
        lucide.createIcons();
    });

    $('#remove-file-btn').on('click', function () {
        $('#attachment-input').val('');
        $('#file-preview-container').addClass('hidden');
        ChatApp.selectedFile = null;
    });

    // Mobile sidebar toggles
    $('#left-sidebar-toggle').on('click', function () {
        $('#left-sidebar').toggleClass('active');
        $('#sidebar-overlay').toggleClass('active');
    });

    $('#right-sidebar-toggle').on('click', function () {
        $('#chat-info-sidebar').toggleClass('active');
        $('#sidebar-overlay').toggleClass('active');
    });

    $('#sidebar-overlay').on('click', function () {
        $('#left-sidebar').removeClass('active');
        $('#chat-info-sidebar').removeClass('active');
        $(this).removeClass('active');
    });

    // Toggle chat info sidebar (desktop)
    $('#toggle-chat-info').on('click', function () {
        const sidebar = $('#chat-info-sidebar');
        const icon = $(this).find('i');

        // Toggle Tailwind classes for hidden state
        if (sidebar.hasClass('w-80')) {
            // Hide
            sidebar.removeClass('w-80').addClass('w-0 p-0 opacity-0 border-none');
            icon.attr('data-lucide', 'info');
        } else {
            // Show
            sidebar.removeClass('w-0 p-0 opacity-0 border-none').addClass('w-80');
            icon.attr('data-lucide', 'x');
        }

        lucide.createIcons();
    });

    // Navigation buttons
    $('#nav-users-btn').on('click', function () {
        $('.nav-icon').removeClass('active');
        $(this).addClass('active');

        // Filter to show only group conversations
        filterConversations('group');
    });

    $('#nav-chats-btn').on('click', function () {
        $('.nav-icon').removeClass('active');
        $(this).addClass('active');

        // Show all conversations
        filterConversations('all');
    });

    $('#nav-notifications-btn').on('click', function () {
        $('.nav-icon').removeClass('active');
        $(this).addClass('active');

        // Show conversations with unread messages
        filterConversations('unread');
    });

    // AI Assistant button
    $('#ai-assistant-btn').on('click', function () {
        $('.nav-icon').removeClass('active');
        $(this).addClass('active');
        openAIAssistant();
    });

    // AI Summary button
    $('#ai-summary-btn').on('click', function () {
        showAISummary();
    });

    // AI Suggestions button
    $('#ai-suggestions-btn').on('click', function () {
        showAISuggestions();
    });

    // Close suggestions
    $('#close-suggestions').on('click', function () {
        $('#ai-suggestions-container').addClass('hidden');
    });
}

/**
 * Filter conversations by type
 */
function filterConversations(filter) {
    const conversations = ChatApp.conversations;

    $('.chat-item').each(function () {
        const convId = $(this).data('conversation-id');
        const conv = conversations.find(c => c.id === convId);

        if (!conv) {
            $(this).hide();
            return;
        }

        let shouldShow = false;

        switch (filter) {
            case 'group':
                shouldShow = conv.type === 'group';
                break;
            case 'all':
                shouldShow = true;
                break;
            case 'unread':
                shouldShow = conv.unread_count > 0;
                break;
            default:
                shouldShow = true;
        }

        if (shouldShow) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

/**
 * Update notification badge with total unread count
 */
function updateNotificationBadge() {
    const totalUnread = ChatApp.conversations.reduce((sum, conv) => sum + (conv.unread_count || 0), 0);
    const badge = $('#notification-badge');

    if (totalUnread > 0) {
        badge.text(totalUnread > 99 ? '99+' : totalUnread);
        badge.removeClass('hidden');
    } else {
        badge.addClass('hidden');
    }
}

/**
 * Open or create AI Assistant conversation
 */
function openAIAssistant() {
    // Check if AI conversation already exists
    $.get(`${CHAT_CONFIG.API_BASE}/ai-assistant/conversation`, function (response) {
        if (response.conversation) {
            // Open existing AI conversation
            selectConversation(response.conversation.id);
        } else {
            // Create new AI conversation by sending first message
            ChatApp.currentConversationId = null;
            ChatApp.aiConversationId = null;

            // Clear messages and show AI welcome
            $('#messages-container').html(`
                <div class="text-center mt-20">
                    <div class="inline-block p-6 bg-gradient-to-r from-purple-100 to-blue-100 rounded-2xl mb-4">
                        <i data-lucide="sparkles" class="w-16 h-16 text-purple-600 mx-auto"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">AI Assistant</h2>
                    <p class="text-gray-600 mb-4">Hello! I'm your AI assistant. How can I help you today?</p>
                </div>
            `);

            // Update header
            $('#chat-header-content').html(`
                <div class="relative">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center">
                        <i data-lucide="sparkles" class="w-6 h-6 text-white"></i>
                    </div>
                </div>
                <div class="flex flex-col">
                    <h2 class="text-lg font-semibold text-gray-900 leading-tight">AI Assistant</h2>
                    <span class="text-xs text-purple-600 font-medium">Always available</span>
                </div>
            `);

            lucide.createIcons();

            // Enable message input for AI
            ChatApp.currentConversationId = 'ai-new';
        }
    }).fail(function (error) {
        console.error('Error loading AI conversation:', error);
        showAlert('Failed to load AI Assistant', 'Error', 'alert-triangle');
    });
}

/**
 * Send message to AI Assistant
 */
function sendAIMessage(message) {
    const conversationId = ChatApp.aiConversationId || null;

    // Show user message immediately
    const userMessageHtml = `
        <div class="flex justify-end mb-4">
            <div class="max-w-[70%]">
                <div class="bg-gradient-to-r from-primary to-secondary text-white rounded-2xl rounded-tr-sm px-4 py-3 shadow-md">
                    <p class="text-sm whitespace-pre-wrap break-words">${escapeHtml(message)}</p>
                </div>
                <p class="text-xs text-gray-400 mt-1 text-right">${formatTime(new Date())}</p>
            </div>
        </div>
    `;

    $('#messages-container').append(userMessageHtml);
    scrollToBottom();

    // Show AI typing indicator
    showAITyping();

    // Send to backend
    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/ai-assistant/message`,
        method: 'POST',
        data: {
            message: message,
            conversation_id: conversationId
        },
        success: function (response) {
            hideAITyping();

            // Store conversation ID
            ChatApp.aiConversationId = response.conversation_id;
            ChatApp.currentConversationId = response.conversation_id;

            // Show AI response
            const aiMessageHtml = `
                <div class="flex justify-start mb-4">
                    <div class="flex items-start space-x-2 max-w-[70%]">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="sparkles" class="w-4 h-4 text-white"></i>
                        </div>
                        <div>
                            <div class="bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm">
                                <p class="text-sm text-gray-800 whitespace-pre-wrap break-words">${escapeHtml(response.ai_message.message)}</p>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">${formatTime(new Date())}</p>
                        </div>
                    </div>
                </div>
            `;

            $('#messages-container').append(aiMessageHtml);
            lucide.createIcons();
            scrollToBottom();

            // Reload conversations to show AI conversation in list
            loadConversations();
        },
        error: function (xhr) {
            hideAITyping();
            const error = xhr.responseJSON?.error || 'Failed to get AI response';
            showAlert(error, 'AI Error', 'alert-triangle');
        }
    });
}

/**
 * Show AI typing indicator
 */
function showAITyping() {
    const typingHtml = `
        <div id="ai-typing-indicator" class="flex justify-start mb-4">
            <div class="flex items-start space-x-2">
                <div class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="sparkles" class="w-4 h-4 text-white"></i>
                </div>
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-2xl rounded-tl-sm px-4 py-3">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-purple-400 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-purple-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-purple-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#messages-container').append(typingHtml);
    lucide.createIcons();
    scrollToBottom();
}

/**
 * Hide AI typing indicator
 */
function hideAITyping() {
    $('#ai-typing-indicator').remove();
}

/**
 * Show AI Summary of current conversation
 */
function showAISummary() {
    if (!ChatApp.currentConversationId || ChatApp.currentConversationId === 'ai-new') {
        showAlert('Please select a conversation first', 'Info', 'info');
        return;
    }

    // Show modal with loading state
    $('#ai-summary-modal').removeClass('hidden');
    $('#summary-content').html(`
        <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
        </div>
    `);
    lucide.createIcons();

    // Fetch summary from backend
    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations/${ChatApp.currentConversationId}/ai-summary`,
        method: 'POST',
        success: function (response) {
            $('#summary-content').html(`
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-lg p-6">
                    <p class="text-gray-800 whitespace-pre-wrap leading-relaxed">${escapeHtml(response.summary)}</p>
                </div>
            `);
        },
        error: function (xhr) {
            const error = xhr.responseJSON?.error || 'Failed to generate summary';
            $('#summary-content').html(`
                <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <p class="text-red-800">${escapeHtml(error)}</p>
                </div>
            `);
        }
    });
}

/**
 * Show AI Reply Suggestions
 */
function showAISuggestions() {
    if (!ChatApp.currentConversationId || ChatApp.currentConversationId === 'ai-new') {
        showAlert('Please select a conversation first', 'Info', 'info');
        return;
    }

    // Show loading state
    $('#ai-suggestions-container').removeClass('hidden');
    $('#suggestions-chips').html(`
        <div class="flex items-center space-x-2 text-purple-600">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-purple-600"></div>
            <span class="text-sm">Generating suggestions...</span>
        </div>
    `);

    // Fetch suggestions from backend
    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations/${ChatApp.currentConversationId}/ai-suggestions`,
        method: 'POST',
        success: function (response) {
            if (response.suggestions && response.suggestions.length > 0) {
                let chipsHtml = '';
                response.suggestions.forEach((suggestion, index) => {
                    // Clean up suggestion text (remove quotes, numbering, etc.)
                    let cleanSuggestion = suggestion.replace(/^["']|["']$/g, '').replace(/^\d+\.\s*/, '').trim();

                    chipsHtml += `
                        <button class="suggestion-chip px-4 py-2 bg-white border-2 border-purple-200 text-purple-700 rounded-full hover:bg-purple-50 hover:border-purple-300 transition text-sm font-medium"
                                data-suggestion="${escapeHtml(cleanSuggestion)}">
                            ${escapeHtml(cleanSuggestion)}
                        </button>
                    `;
                });
                $('#suggestions-chips').html(chipsHtml);

                // Add click handlers
                $('.suggestion-chip').on('click', function () {
                    const suggestion = $(this).data('suggestion');
                    $('#message-input').val(suggestion).focus();
                    $('#ai-suggestions-container').addClass('hidden');
                });
            } else {
                $('#suggestions-chips').html('<p class="text-gray-500 text-sm">No suggestions available</p>');
            }

            lucide.createIcons();
        },
        error: function (xhr) {
            const error = xhr.responseJSON?.error || 'Failed to generate suggestions';
            $('#suggestions-chips').html(`<p class="text-red-600 text-sm">${escapeHtml(error)}</p>`);
        }
    });
}

/**
 * Initialize emoji picker
 */
function initEmojiPicker() {
    function tryInit() {
        if (window.EmojiPicker && !ChatApp.emojiPickerInstance) {
            ChatApp.emojiPickerInstance = new window.EmojiPicker();

            ChatApp.emojiPickerInstance.style.position = 'absolute';
            ChatApp.emojiPickerInstance.style.bottom = '100%';
            ChatApp.emojiPickerInstance.style.left = '0';
            ChatApp.emojiPickerInstance.style.marginBottom = '10px';
            ChatApp.emojiPickerInstance.style.zIndex = '1000';
            ChatApp.emojiPickerInstance.style.display = 'none';
            ChatApp.emojiPickerInstance.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.15)';
            ChatApp.emojiPickerInstance.style.borderRadius = '8px';

            const container = $('.bg-white.border-t.border-gray-200.p-4');
            container.css('position', 'relative');
            container.append(ChatApp.emojiPickerInstance);

            ChatApp.emojiPickerInstance.addEventListener('emoji-click', event => {
                const input = $('#message-input');
                input.val(input.val() + event.detail.unicode);
                input.focus();
            });
        }
    }

    setTimeout(tryInit, 500);

    $('#emoji-btn').on('click', function (e) {
        e.stopPropagation();
        if (ChatApp.emojiPickerInstance) {
            const isHidden = ChatApp.emojiPickerInstance.style.display === 'none';
            ChatApp.emojiPickerInstance.style.display = isHidden ? 'block' : 'none';
        } else {
            tryInit();
            setTimeout(() => {
                if (ChatApp.emojiPickerInstance) {
                    ChatApp.emojiPickerInstance.style.display = 'block';
                }
            }, 100);
        }
    });

    $(document).on('click', function (e) {
        if (ChatApp.emojiPickerInstance && !$(e.target).closest('emoji-picker, #emoji-btn').length) {
            ChatApp.emojiPickerInstance.style.display = 'none';
        }
    });
}

/**
 * Utility: Format file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Utility: Format time
 */
function formatTime(date) {
    let hours = date.getHours();
    let minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0' + minutes : minutes;

    return `${hours}:${minutes} ${ampm}`;
}

/**
 * Utility: Escape HTML
 */
function escapeHtml(text) {
    if (!text) return ''; // Handle null, undefined, or empty string
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Utility: Scroll to bottom
 */
function scrollToBottom() {
    const container = $('#messages-container');
    if (container.length > 0) {
        // Use setTimeout to ensure DOM is updated
        setTimeout(() => {
            container[0].scrollTop = container[0].scrollHeight;
        }, 50);
    }
}

/**
 * Mark message as read
 */
function markMessageAsRead(messageId) {
    if (!messageId) return;

    $.post(`${CHAT_CONFIG.API_BASE}/messages/${messageId}/read`, function (response) {


        // Update unread count from server response
        if (ChatApp.currentConversationId && response.unread_count !== undefined) {
            const chatItem = $(`.chat-item[data-conversation-id="${ChatApp.currentConversationId}"]`);
            const badge = chatItem.find('.unread-badge');
            const unreadCount = response.unread_count;

            if (unreadCount === 0) {
                badge.remove();
            } else {
                if (badge.length > 0) {
                    badge.text(unreadCount);
                } else {
                    chatItem.find('.flex-1 .flex.items-center.justify-between').append(
                        `<span class="unread-badge bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center">${unreadCount}</span>`
                    );
                }
            }

            // Update in conversations array
            const conv = ChatApp.conversations.find(c => c.id === ChatApp.currentConversationId);
            if (conv) {
                conv.unread_count = unreadCount;
            }
        }
    }).fail(function (error) {
        // Silently fail if already read or other error

    });
}

/**
 * Update conversation unread count in the list
 */
function updateConversationUnreadCount(conversationId) {
    // Fetch updated conversation data from server to get accurate unread count
    $.get(`${CHAT_CONFIG.API_BASE}/conversations`, function (conversations) {
        const conversation = conversations.find(c => c.id === conversationId);

        if (conversation) {
            const chatItem = $(`.chat-item[data-conversation-id="${conversationId}"]`);
            const badge = chatItem.find('.unread-badge');
            const unreadCount = conversation.unread_count || 0;

            if (unreadCount === 0) {
                badge.remove();
            } else {
                if (badge.length > 0) {
                    badge.text(unreadCount);
                } else {
                    chatItem.find('.flex-1 .flex.items-center.justify-between').append(
                        `<span class="unread-badge bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center">${unreadCount}</span>`
                    );
                }
            }

            // Update in conversations array
            const conv = ChatApp.conversations.find(c => c.id === conversationId);
            if (conv) {
                conv.unread_count = unreadCount;
            }
        }
    }).fail(function (error) {
        console.error('Error fetching conversation unread count:', error);
    });
}

/**
 * Update message read status in UI
 */
function updateMessageReadStatus(messageId, userId) {
    const messageElement = $(`.message-bubble`).closest(`[data-message-id="${messageId}"]`);

    if (messageElement.length > 0) {
        // Find existing read receipt or create new one
        let readReceipt = messageElement.find('.flex.items-center.space-x-1.mt-1');

        if (readReceipt.length > 0) {
            // Update existing receipt to show read status
            readReceipt.html(`
                <i data-lucide="check-check" class="w-3 h-3 text-blue-500"></i>
                <span class="text-xs text-blue-500">Read</span>
            `);
        } else {
            // Add read receipt
            const receiptHtml = `
                <div class="flex items-center space-x-1 mt-1">
                    <i data-lucide="check-check" class="w-3 h-3 text-blue-500"></i>
                    <span class="text-xs text-blue-500">Read</span>
                </div>
            `;
            messageElement.find('.flex-1.flex.flex-col.items-end').append(receiptHtml);
        }

        lucide.createIcons();
    }
}

// Make functions globally available
window.showFileCategory = showFileCategory;
window.confirmDeleteChat = confirmDeleteChat;
window.initChatApp = initChatApp;
window.openEditGroupModal = openEditGroupModal;
window.openAddMemberModal = openAddMemberModal;
window.removeMemberFromGroup = removeMemberFromGroup;
window.leaveGroup = leaveGroup;
window.showImagePreview = showImagePreview;

/**
 * Open Edit Group Modal
 */
function openEditGroupModal() {
    if (!ChatApp.currentConversationId) return;

    const conversation = ChatApp.conversations.find(c => c.id === ChatApp.currentConversationId);
    if (!conversation) return;

    $('#edit-group-name').val(conversation.name);

    const avatar = conversation.avatar
        ? `/storage/${conversation.avatar}`
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(conversation.name)}&background=8b5cf6&color=fff`;

    $('#edit-group-avatar-preview').attr('src', avatar);
    $('#edit-group-modal').fadeIn(200);

    // Bind update button
    $('#update-group-btn').off('click').on('click', updateGroup);

    // Bind avatar change
    $('#edit-group-avatar').off('change').on('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#edit-group-avatar-preview').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });
}

/**
 * Update Group
 */
function updateGroup() {
    const conversationId = ChatApp.currentConversationId;
    const name = $('#edit-group-name').val().trim();
    const avatarInput = $('#edit-group-avatar')[0];

    if (!name) {
        showAlert('Group name is required', 'Validation Error');
        return;
    }

    const formData = new FormData();
    formData.append('name', name);
    formData.append('_method', 'PUT'); // Laravel method spoofing

    if (avatarInput.files.length > 0) {
        formData.append('avatar', avatarInput.files[0]);
    }

    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations/${conversationId}`,
        method: 'POST', // Using POST with _method=PUT for file upload
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            $('#edit-group-modal').fadeOut(200);

            // Update local conversation data
            const conversation = ChatApp.conversations.find(c => c.id === conversationId);
            if (conversation) {
                conversation.name = response.name;
                conversation.avatar = response.avatar;
            }

            // Refresh UI
            loadConversations();
            updateChatInfo(conversation);
            $('#chat-header-title').text(response.name);
        },
        error: function (error) {
            console.error('Error updating group:', error);
            showAlert('Failed to update group', 'Error', 'alert-triangle');
        }
    });
}

/**
 * Open Add Member Modal
 */
function openAddMemberModal() {
    if (!ChatApp.currentConversationId) return;

    const conversation = ChatApp.conversations.find(c => c.id === ChatApp.currentConversationId);
    if (!conversation) return;

    // Filter out existing members
    const existingMemberIds = conversation.participants.map(p => p.id);
    const availableUsers = ChatApp.users.filter(u => !existingMemberIds.includes(u.id));

    const container = $('#add-member-list');
    container.empty();

    if (availableUsers.length === 0) {
        container.html('<div class="text-center text-gray-500 py-4">No users available to add</div>');
    } else {
        availableUsers.forEach(user => {
            const avatar = user.avatar
                ? `/storage/${user.avatar}`
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=724FF9&color=fff`;

            const html = `
                <label class="user-select-item">
                    <input type="checkbox" class="add-member-checkbox" value="${user.id}">
                    <div class="relative">
                        <img src="${avatar}" alt="${user.name}" class="w-10 h-10 rounded-full object-cover">
                    </div>
                    <span class="flex-1 text-sm font-medium text-gray-900">${escapeHtml(user.name)}</span>
                    <i data-lucide="check" class="w-5 h-5 text-secondary checkmark"></i>
                </label>
            `;
            container.append(html);
        });
    }

    lucide.createIcons();
    $('#add-member-count').text('0');
    $('#add-member-modal').fadeIn(200);

    // Bind checkbox change
    $('.add-member-checkbox').on('change', function () {
        const count = $('.add-member-checkbox:checked').length;
        $('#add-member-count').text(count);
    });

    // Bind submit button
    $('#add-member-submit').off('click').on('click', addMemberToGroup);
}

/**
 * Add Member to Group
 */
function addMemberToGroup() {
    const conversationId = ChatApp.currentConversationId;
    const selectedUserIds = [];

    $('.add-member-checkbox:checked').each(function () {
        selectedUserIds.push($(this).val());
    });

    if (selectedUserIds.length === 0) {
        showAlert('Please select at least one user', 'Validation Error');
        return;
    }

    $.ajax({
        url: `${CHAT_CONFIG.API_BASE}/conversations/${conversationId}/participants`,
        method: 'POST',
        data: { user_ids: selectedUserIds },
        success: function (response) {
            $('#add-member-modal').fadeOut(200);

            // Update local conversation data
            const conversation = ChatApp.conversations.find(c => c.id === conversationId);
            if (conversation) {
                // Assuming response returns the updated participants list or the added participants
                // For simplicity, let's reload the conversation details or just push if we have the user objects
                // Better to reload conversations to get fresh data
                loadConversations();

                // Also update the current view if we are still on it
                // We need to fetch the updated conversation details
                $.get(`${CHAT_CONFIG.API_BASE}/conversations`, function (data) {
                    ChatApp.conversations = data;
                    const updatedConv = data.find(c => c.id === conversationId);
                    if (updatedConv) {
                        updateChatInfo(updatedConv);
                    }
                });
            }
        },
        error: function (error) {
            console.error('Error adding members:', error);
            showAlert('Failed to add members', 'Error', 'alert-triangle');
        }
    });
}

/**
 * Remove Member from Group
 */
/**
 * Remove Member from Group
 */
function removeMemberFromGroup(userId) {
    showConfirmationModal({
        title: 'Remove Member',
        message: 'Are you sure you want to remove this member?',
        icon: 'user-minus',
        confirmText: 'Remove',
        confirmColor: 'bg-red-600 text-white hover:bg-red-700',
        onConfirm: function () {
            const conversationId = ChatApp.currentConversationId;

            $.ajax({
                url: `${CHAT_CONFIG.API_BASE}/conversations/${conversationId}/participants/${userId}/remove`,
                method: 'POST',
                success: function (response) {
                    // Update local data and UI
                    const conversation = ChatApp.conversations.find(c => c.id === conversationId);
                    if (conversation) {
                        conversation.participants = conversation.participants.filter(p => p.id !== userId);
                        updateChatInfo(conversation);

                        // Also reload conversations list to update member count
                        loadConversations();
                    }
                },
                error: function (error) {
                    console.error('Error removing member:', error);
                    // We'll replace this alert later or use a simple modal
                    showConfirmationModal({
                        title: 'Error',
                        message: 'Failed to remove member',
                        icon: 'alert-triangle',
                        confirmText: 'OK',
                        confirmColor: 'bg-gray-600 text-white hover:bg-gray-700',
                        onConfirm: null // Just close
                    });
                }
            });
        }
    });
}

/**
 * Leave Group
 */
/**
 * Leave Group
 */
function leaveGroup() {
    showConfirmationModal({
        title: 'Leave Group',
        message: 'Are you sure you want to leave this group?',
        icon: 'log-out',
        confirmText: 'Leave',
        confirmColor: 'bg-red-600 text-white hover:bg-red-700',
        onConfirm: function () {
            const conversationId = ChatApp.currentConversationId;

            $.ajax({
                url: `${CHAT_CONFIG.API_BASE}/conversations/${conversationId}/leave`,
                method: 'POST',
                success: function (response) {
                    // Remove conversation from list
                    $(`.chat-item[data-conversation-id="${conversationId}"]`).remove();
                    ChatApp.conversations = ChatApp.conversations.filter(c => c.id !== conversationId);

                    // Clear current view
                    ChatApp.currentConversationId = null;
                    $('#messages-container').html(`
                        <div class="text-center text-gray-500 mt-20">
                            <i data-lucide="message-circle" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                            <p>Select a conversation to start chatting</p>
                        </div>
                    `);
                    $('#chat-header-title').text('Select a conversation');
                    $('#chat-info-content').html('<div class="text-center text-gray-500">Select a conversation to view details</div>');
                },
                error: function (error) {
                    console.error('Error leaving group:', error);
                    showConfirmationModal({
                        title: 'Error',
                        message: 'Failed to leave group',
                        icon: 'alert-triangle',
                        confirmText: 'OK',
                        confirmColor: 'bg-gray-600 text-white hover:bg-gray-700',
                        onConfirm: null
                    });
                }
            });
        }
    });
}

/**
 * Show Confirmation Modal
 * @param {Object} options
 * @param {string} options.title - Modal title
 * @param {string} options.message - Modal message
 * @param {string} options.icon - Lucide icon name (default: alert-triangle)
 * @param {string} options.confirmText - Confirm button text (default: Confirm)
 * @param {string} options.confirmColor - Confirm button color class (default: bg-primary text-white)
 * @param {boolean} options.showCancelButton - Whether to show cancel button (default: true)
 * @param {Function} options.onConfirm - Callback when confirmed
 */
function showConfirmationModal(options) {
    const {
        title = 'Confirmation',
        message = 'Are you sure?',
        icon = 'alert-triangle',
        confirmText = 'Confirm',
        confirmColor = 'bg-gradient-to-r from-primary to-secondary text-white hover:from-secondary hover:to-secondary-dark',
        showCancelButton = true,
        onConfirm
    } = options;

    $('#confirmation-title').text(title);
    $('#confirmation-message').text(message);

    // Update icon
    const iconEl = $('#confirmation-icon');
    iconEl.attr('data-lucide', icon);

    // Update confirm button
    const confirmBtn = $('#confirmation-confirm-btn');
    confirmBtn.text(confirmText);
    confirmBtn.attr('class', `flex-1 px-4 py-3 rounded-lg transition shadow-lg hover:shadow-xl font-medium ${confirmColor}`);

    // Show/Hide cancel button
    const cancelBtn = $('#confirmation-cancel-btn');
    if (showCancelButton) {
        cancelBtn.show();
    } else {
        cancelBtn.hide();
    }

    // Show modal
    $('#confirmation-modal').fadeIn(200);
    lucide.createIcons();

    // Bind events
    confirmBtn.off('click').on('click', function () {
        if (onConfirm) onConfirm();
        $('#confirmation-modal').fadeOut(200);
    });

    cancelBtn.off('click').on('click', function () {
        $('#confirmation-modal').fadeOut(200);
    });
}

/**
 * Show Alert Modal
 * @param {string} message
 * @param {string} title
 * @param {string} icon
 */
function showAlert(message, title = 'Alert', icon = 'info') {
    showConfirmationModal({
        title: title,
        message: message,
        icon: icon,
        confirmText: 'OK',
        confirmColor: 'bg-gray-800 text-white hover:bg-gray-900',
        showCancelButton: false
    });
}

// Make functions globally available
window.showFileCategory = showFileCategory;
window.confirmDeleteChat = confirmDeleteChat;
window.initChatApp = initChatApp;
window.openEditGroupModal = openEditGroupModal;
window.openAddMemberModal = openAddMemberModal;
window.removeMemberFromGroup = removeMemberFromGroup;
window.leaveGroup = leaveGroup;
window.showImagePreview = showImagePreview;
window.showConfirmationModal = showConfirmationModal;
window.showAlert = showAlert;
