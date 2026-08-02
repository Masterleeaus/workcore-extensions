(() => {
    'use strict';

    const DB_NAME = 'titan-zero-operations';
    const DB_VERSION = 1;
    const STORE = 'operations';

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    const store = db.createObjectStore(STORE, { keyPath: 'operation_id' });
                    store.createIndex('status', 'status', { unique: false });
                    store.createIndex('created_at', 'created_at', { unique: false });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function transact(mode, callback) {
        const db = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(STORE, mode);
            const store = transaction.objectStore(STORE);
            callback(store, resolve, reject);
            transaction.onerror = () => reject(transaction.error);
            transaction.oncomplete = () => db.close();
        });
    }

    async function enqueue(action, payload = {}, options = {}) {
        const operation = {
            operation_id: options.operationId || crypto.randomUUID(),
            device_id: options.deviceId || getDeviceId(),
            action,
            payload,
            occurred_at: options.occurredAt || new Date().toISOString(),
            confirmation_id: options.confirmationId || null,
            status: 'pending',
            attempts: 0,
            created_at: new Date().toISOString(),
            last_error: null,
        };

        await transact('readwrite', (store, resolve, reject) => {
            const request = store.put(operation);
            request.onsuccess = () => resolve(operation);
            request.onerror = () => reject(request.error);
        });

        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register('titan-zero-sync');
        } else if (navigator.onLine) {
            void flush();
        }

        return operation;
    }

    async function pending() {
        return transact('readonly', (store, resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result.filter(item => item.status !== 'completed'));
            request.onerror = () => reject(request.error);
        });
    }

    async function update(operation) {
        return transact('readwrite', (store, resolve, reject) => {
            const request = store.put(operation);
            request.onsuccess = () => resolve(operation);
            request.onerror = () => reject(request.error);
        });
    }

    async function flush() {
        const operations = await pending();
        for (const operation of operations) {
            operation.status = 'syncing';
            operation.attempts += 1;
            await update(operation);

            try {
                const response = await fetch('/api/v1/sync/operations', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(operation),
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => ({}));
                    throw new Error(error.message || `Sync failed with HTTP ${response.status}`);
                }

                operation.status = 'completed';
                operation.result = await response.json();
                operation.completed_at = new Date().toISOString();
                operation.last_error = null;
            } catch (error) {
                operation.status = 'failed';
                operation.last_error = String(error?.message || error);
            }

            await update(operation);
        }
    }

    function getDeviceId() {
        let id = localStorage.getItem('titan_zero_device_id');
        if (!id) {
            id = crypto.randomUUID();
            localStorage.setItem('titan_zero_device_id', id);
        }
        return id;
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data?.type === 'TITAN_ZERO_FLUSH_OPERATIONS') void flush();
        });
    }

    window.addEventListener('online', () => void flush());
    window.TitanOfflineOperations = { enqueue, flush, pending };
})();
