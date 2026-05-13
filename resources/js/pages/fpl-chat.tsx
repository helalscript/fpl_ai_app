import { Head, router, usePage } from '@inertiajs/react';
import {
    Bot,
    ChevronDown,
    Loader2,
    MessageSquarePlus,
    SendHorizontal,
    Sparkles,
    UserRound,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { KeyboardEvent, UIEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import fpl from '@/routes/fpl';
import fplChat from '@/routes/fpl/chat';
import { update } from '@/routes/profile';

const FPL_CHAT_PER_PAGE = 40;

type ChatRole = 'user' | 'assistant';

type ChatMessage = {
    id: string;
    role: ChatRole;
    content: string;
    streaming?: boolean;
};

type ServerChatMessage = {
    id: number;
    role: ChatRole;
    content: string;
    created_at: string;
};

type PromptSuggestion = {
    id: number;
    body: string;
};

type ConversationSummary = {
    id: number;
    last_message_at: string | null;
    created_at: string;
};

type FplChatPageProps = {
    csrf_token?: string;
    auth: { user: { id: number; name: string; email: string; fpl_team_id: number | null } };
    promptSuggestions?: PromptSuggestion[];
    initialThreadId?: number | null;
    initialConversations?: ConversationSummary[];
    initialMessages?: ServerChatMessage[];
    initialHasMoreOlder?: boolean;
};

type MessagesPageResponse = {
    messages: ServerChatMessage[];
    has_more_older: boolean;
    has_more_newer: boolean;
};

type ConversationsIndexResponse = {
    conversations: ConversationSummary[];
};

type ConversationStoreResponse = {
    conversation: ConversationSummary;
};

function mapServerMessages(rows: ServerChatMessage[]): ChatMessage[] {
    return rows.map((m) => ({
        id: `db-${m.id}`,
        role: m.role,
        content: m.content,
        streaming: false,
    }));
}

function chatDbNumericId(id: string): number | null {
    if (!id.startsWith('db-')) {
        return null;
    }

    const n = parseInt(id.slice(3), 10);

    return Number.isFinite(n) ? n : null;
}

function sortChatMessages(msgs: ChatMessage[]): ChatMessage[] {
    return [...msgs].sort((a, b) => {
        const na = chatDbNumericId(a.id);
        const nb = chatDbNumericId(b.id);

        if (na !== null && nb !== null) {
            return na - nb;
        }

        if (na !== null) {
            return -1;
        }

        if (nb !== null) {
            return 1;
        }

        return 0;
    });
}

function mergeMessagesWithIncoming(
    current: ChatMessage[],
    incoming: ChatMessage[],
    dropIds: Set<string>,
): ChatMessage[] {
    const map = new Map<string, ChatMessage>();

    for (const m of current) {
        if (!dropIds.has(m.id)) {
            map.set(m.id, m);
        }
    }

    for (const m of incoming) {
        map.set(m.id, m);
    }

    return sortChatMessages(Array.from(map.values()));
}

function readXsrfTokenFromCookie(): string | null {
    const row = document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='));

    if (!row) {
        return null;
    }

    return decodeURIComponent(row.slice('XSRF-TOKEN='.length));
}

function parsePositiveTeamId(value: string): number | null {
    const t = value.trim();

    if (t === '') {
        return null;
    }

    const n = parseInt(t, 10);

    if (!Number.isFinite(n) || n < 1) {
        return null;
    }

    return n;
}

function flattenErrors(errors: Record<string, string | string[]>): Record<string, string> {
    const out: Record<string, string> = {};

    for (const [key, val] of Object.entries(errors)) {
        out[key] = Array.isArray(val) ? (val[0] ?? '') : val;
    }

    return out;
}

function randomId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `m-${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

function formatRequestError(data: unknown, status: number): string {
    if (data !== null && typeof data === 'object') {
        const o = data as Record<string, unknown>;

        if (typeof o.message === 'string' && o.message !== '') {
            return o.message;
        }

        const errs = o.errors;

        if (errs !== null && typeof errs === 'object') {
            const first = Object.values(errs as Record<string, string[] | string>)[0];

            if (Array.isArray(first) && first[0]) {
                return String(first[0]);
            }

            if (typeof first === 'string') {
                return first;
            }
        }
    }

    return `Request failed (${status})`;
}

function formatThreadOptionLabel(row: ConversationSummary): string {
    const stamp = row.last_message_at ?? row.created_at;

    if (!stamp) {
        return `Chat #${row.id}`;
    }

    try {
        const d = new Date(stamp);

        if (Number.isNaN(d.getTime())) {
            return `Chat #${row.id}`;
        }

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(d);
    } catch {
        return `Chat #${row.id}`;
    }
}

export default function FplChat() {
    const page = usePage<FplChatPageProps>();
    const csrfToken = page.props.csrf_token;
    const auth = page.props.auth;
    const promptSuggestions = page.props.promptSuggestions ?? [];

    const [messages, setMessages] = useState<ChatMessage[]>(() =>
        mapServerMessages(page.props.initialMessages ?? []),
    );
    const [draft, setDraft] = useState('');
    const [teamIdInput, setTeamIdInput] = useState(() =>
        auth.user.fpl_team_id != null ? String(auth.user.fpl_team_id) : '',
    );
    const [settingsOpen, setSettingsOpen] = useState(
        () => parsePositiveTeamId(auth.user.fpl_team_id != null ? String(auth.user.fpl_team_id) : '') === null,
    );
    const [streaming, setStreaming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [savingProfile, setSavingProfile] = useState(false);
    const [profileFieldErrors, setProfileFieldErrors] = useState<Record<string, string>>({});
    const [profileBanner, setProfileBanner] = useState<string | null>(null);
    const [hasMoreOlder, setHasMoreOlder] = useState(() => page.props.initialHasMoreOlder ?? false);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [threadId, setThreadId] = useState<number | null>(() => page.props.initialThreadId ?? null);
    const [conversationsList, setConversationsList] = useState<ConversationSummary[]>(
        () => page.props.initialConversations ?? [],
    );
    const [loadingConvos, setLoadingConvos] = useState(false);
    const [loadingMessages, setLoadingMessages] = useState(false);
    const [creatingChat, setCreatingChat] = useState(false);

    const effectiveTeamId = useMemo(() => parsePositiveTeamId(teamIdInput), [teamIdInput]);

    const profileTeamMismatch = useMemo(() => {
        const saved = auth.user.fpl_team_id;

        if (saved == null || effectiveTeamId === null) {
            return false;
        }

        return saved !== effectiveTeamId;
    }, [auth.user.fpl_team_id, effectiveTeamId]);

    const scrollAnchorRef = useRef<HTMLDivElement>(null);
    const scrollAreaRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const skipScrollToBottomRef = useRef(false);
    const prevThreadIdRef = useRef<number | null>(null);
    const streamingRef = useRef(false);
    const threadIdRef = useRef<number | null>(null);
    const effectiveTeamIdRef = useRef<number | null>(null);

    useEffect(() => {
        streamingRef.current = streaming;
        threadIdRef.current = threadId;
        effectiveTeamIdRef.current = effectiveTeamId;
    }, [effectiveTeamId, streaming, threadId]);

    useEffect(() => {
        setTeamIdInput(auth.user.fpl_team_id != null ? String(auth.user.fpl_team_id) : '');
    }, [auth.user.fpl_team_id]);

    useEffect(() => {
        if (effectiveTeamId === null || profileTeamMismatch) {
            setThreadId(null);
            setConversationsList([]);
            setLoadingConvos(false);
            prevThreadIdRef.current = null;
            setMessages([]);
            setHasMoreOlder(false);

            return;
        }

        const ac = new AbortController();
        setLoadingConvos(true);

        void (async () => {
            try {
                const response = await fetch(
                    fplChat.conversations.index.url({ query: { team_id: effectiveTeamId } }),
                    {
                        signal: ac.signal,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    const data = await response.json().catch(() => null);

                    if (!ac.signal.aborted) {
                        setError(formatRequestError(data, response.status));
                    }

                    return;
                }

                const payload = (await response.json()) as ConversationsIndexResponse;
                let list = payload.conversations ?? [];

                if (list.length === 0) {
                    if (!csrfToken) {
                        if (!ac.signal.aborted) {
                            setError('Security token missing. Please refresh the page and try again.');
                        }

                        return;
                    }

                    const xsrf = readXsrfTokenFromCookie();
                    const createResponse = await fetch(fplChat.conversations.store.url(), {
                        method: 'POST',
                        signal: ac.signal,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                        },
                        body: JSON.stringify({ team_id: effectiveTeamId }),
                    });

                    if (!createResponse.ok) {
                        const data = await createResponse.json().catch(() => null);

                        if (!ac.signal.aborted) {
                            setError(formatRequestError(data, createResponse.status));
                        }

                        return;
                    }

                    const created = (await createResponse.json()) as ConversationStoreResponse;
                    list = [created.conversation];
                }

                if (ac.signal.aborted) {
                    return;
                }

                setError(null);
                setConversationsList(list);
                setThreadId((prev) => {
                    if (prev !== null && list.some((c) => c.id === prev)) {
                        return prev;
                    }

                    return list[0]?.id ?? null;
                });
            } catch (e) {
                if (e instanceof Error && e.name === 'AbortError') {
                    return;
                }

                if (!ac.signal.aborted) {
                    setError(e instanceof Error ? e.message : 'Failed to load chat threads.');
                }
            } finally {
                if (!ac.signal.aborted) {
                    setLoadingConvos(false);
                }
            }
        })();

        return () => ac.abort();
    }, [csrfToken, effectiveTeamId, profileTeamMismatch]);

    useEffect(() => {
        if (effectiveTeamId === null || profileTeamMismatch || threadId === null) {
            return;
        }

        const ac = new AbortController();
        const prev = prevThreadIdRef.current;
        const threadChanged = prev !== null && prev !== threadId;
        prevThreadIdRef.current = threadId;

        if (threadChanged) {
            setMessages([]);
            setHasMoreOlder(false);
        }

        setLoadingMessages(true);

        void (async () => {
            try {
                const response = await fetch(
                    fplChat.messages.index.url({
                        query: {
                            team_id: effectiveTeamId,
                            thread_id: threadId,
                            per_page: FPL_CHAT_PER_PAGE,
                        },
                    }),
                    {
                        signal: ac.signal,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    const data = await response.json().catch(() => null);

                    if (!ac.signal.aborted) {
                        setError(formatRequestError(data, response.status));
                    }

                    return;
                }

                const data = (await response.json()) as MessagesPageResponse;

                if (ac.signal.aborted) {
                    return;
                }

                setError(null);

                if (streamingRef.current) {
                    setMessages((prev) =>
                        mergeMessagesWithIncoming(prev, mapServerMessages(data.messages ?? []), new Set()),
                    );
                } else {
                    setMessages(mapServerMessages(data.messages ?? []));
                }

                setHasMoreOlder(data.has_more_older);
            } catch (e) {
                if (e instanceof Error && e.name === 'AbortError') {
                    return;
                }

                if (!ac.signal.aborted) {
                    setError(e instanceof Error ? e.message : 'Failed to load chat history.');
                }
            } finally {
                if (!ac.signal.aborted) {
                    setLoadingMessages(false);
                }
            }
        })();

        return () => ac.abort();
    }, [effectiveTeamId, profileTeamMismatch, threadId]);

    const loadOlder = useCallback(async () => {
        if (effectiveTeamId === null || threadId === null || !hasMoreOlder || loadingOlder) {
            return;
        }

        const dbIds = messages
            .map((m) => chatDbNumericId(m.id))
            .filter((n): n is number => n !== null);

        if (dbIds.length === 0) {
            return;
        }

        const beforeId = Math.min(...dbIds);
        const el = scrollAreaRef.current;
        const prevHeight = el?.scrollHeight ?? 0;
        const prevTop = el?.scrollTop ?? 0;

        skipScrollToBottomRef.current = true;
        setLoadingOlder(true);

        try {
            const response = await fetch(
                fplChat.messages.index.url({
                    query: {
                        team_id: effectiveTeamId,
                        thread_id: threadId,
                        per_page: FPL_CHAT_PER_PAGE,
                        before_id: beforeId,
                    },
                }),
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            const data = (await response.json()) as MessagesPageResponse;
            const older = mapServerMessages(data.messages ?? []);

            setMessages((prev) => sortChatMessages([...older, ...prev]));
            setHasMoreOlder(data.has_more_older);

            requestAnimationFrame(() => {
                const box = scrollAreaRef.current;

                if (box) {
                    box.scrollTop = box.scrollHeight - prevHeight + prevTop;
                }
            });
        } catch {
            //
        } finally {
            setLoadingOlder(false);
        }
    }, [effectiveTeamId, hasMoreOlder, loadingOlder, messages, threadId]);

    const onMessagesScroll = useCallback(
        (e: UIEvent<HTMLDivElement>) => {
            if (!hasMoreOlder || loadingOlder) {
                return;
            }

            if (e.currentTarget.scrollTop > 140) {
                return;
            }

            void loadOlder();
        },
        [hasMoreOlder, loadOlder, loadingOlder],
    );

    const clearConversation = useCallback(async () => {
        if (effectiveTeamId === null || threadId === null) {
            return;
        }

        if (!csrfToken) {
            setError('Security token missing. Please refresh the page and try again.');

            return;
        }

        const xsrf = readXsrfTokenFromCookie();

        try {
            const response = await fetch(
                fplChat.messages.destroy.url({ query: { team_id: effectiveTeamId, thread_id: threadId } }),
                {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                    },
                },
            );

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                setError(formatRequestError(data, response.status));

                return;
            }

            setMessages([]);
            setHasMoreOlder(false);
            setError(null);
            setConversationsList((prev) =>
                prev.map((c) => (c.id === threadId ? { ...c, last_message_at: null } : c)),
            );
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to clear history.');
        }
    }, [csrfToken, effectiveTeamId, threadId]);

    const createNewChat = useCallback(async () => {
        if (effectiveTeamId === null || profileTeamMismatch || !csrfToken) {
            return;
        }

        const xsrf = readXsrfTokenFromCookie();
        setCreatingChat(true);
        setError(null);

        try {
            const response = await fetch(fplChat.conversations.store.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                },
                body: JSON.stringify({ team_id: effectiveTeamId }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => null);
                setError(formatRequestError(data, response.status));

                return;
            }

            const payload = (await response.json()) as ConversationStoreResponse;
            const row = payload.conversation;

            setConversationsList((prev) => [row, ...prev.filter((c) => c.id !== row.id)]);
            setThreadId(row.id);
            setMessages([]);
            setHasMoreOlder(false);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to start a new chat.');
        } finally {
            setCreatingChat(false);
        }
    }, [csrfToken, effectiveTeamId, profileTeamMismatch]);

    const scrollToBottom = useCallback(() => {
        scrollAnchorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, []);

    useEffect(() => {
        if (skipScrollToBottomRef.current) {
            skipScrollToBottomRef.current = false;

            return;
        }

        scrollToBottom();
    }, [messages, scrollToBottom]);

    const appendSsePayload = useCallback((rawLine: string, onDelta: (d: string) => void) => {
        const line = rawLine.replace(/\r$/, '');

        if (!line.startsWith('data: ')) {
            return;
        }

        const payload = line.slice(6).trim();

        if (payload === '' || payload === '[DONE]') {
            return;
        }

        try {
            const parsed = JSON.parse(payload) as { type?: string; delta?: string };

            if (parsed.type === 'text_delta' && typeof parsed.delta === 'string') {
                onDelta(parsed.delta);
            }
        } catch {
            // ignore malformed SSE JSON lines
        }
    }, []);

    const saveTeamIdToProfile = useCallback(() => {
        setProfileBanner(null);
        setProfileFieldErrors({});

        if (teamIdInput.trim() !== '' && effectiveTeamId === null) {
            setProfileFieldErrors({ fpl_team_id: 'Enter a valid positive FPL team ID, or leave empty to clear.' });

            return;
        }

        setSavingProfile(true);

        router.patch(
            update.url(),
            {
                name: auth.user.name,
                email: auth.user.email,
                fpl_team_id: effectiveTeamId,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSavingProfile(false);
                },
                onSuccess: () => {
                    setProfileBanner('Saved to your profile.');
                },
                onError: (errs) => {
                    setProfileFieldErrors(flattenErrors(errs));
                },
            },
        );
    }, [auth.user.email, auth.user.name, effectiveTeamId, teamIdInput]);

    const sendMessageWithText = useCallback(
        async (rawText: string) => {
            const text = rawText.trim();

            if (!text || streaming || effectiveTeamId === null || threadId === null) {
                return;
            }

            if (profileTeamMismatch) {
                return;
            }

            if (!csrfToken) {
                setError('Security token missing. Please refresh the page and try again.');

                return;
            }

            const dbNums = messages
                .map((m) => chatDbNumericId(m.id))
                .filter((n): n is number => n !== null);
            const maxDbIdBeforeSend = dbNums.length > 0 ? Math.max(...dbNums) : 0;

            const userId = randomId();
            const assistantId = randomId();

            const userMessage: ChatMessage = { id: userId, role: 'user', content: text };
            const assistantMessage: ChatMessage = {
                id: assistantId,
                role: 'assistant',
                content: '',
                streaming: true,
            };

            setMessages((prev) => [...prev, userMessage, assistantMessage]);
            setDraft('');
            setError(null);
            setStreaming(true);

            const xsrf = readXsrfTokenFromCookie();
            const streamUrl = fplChat.stream.url();

            try {
                const response = await fetch(streamUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/event-stream',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                        ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                    },
                    body: JSON.stringify({
                        message: text,
                        team_id: effectiveTeamId,
                        thread_id: threadId,
                    }),
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => null);
                    setError(formatRequestError(data, response.status));
                    setMessages((prev) => prev.filter((m) => m.id !== assistantId && m.id !== userId));
                    setStreaming(false);

                    return;
                }

                const reader = response.body?.getReader();

                if (!reader) {
                    setError('Streaming is not supported in this browser.');
                    setMessages((prev) => prev.filter((m) => m.id !== assistantId && m.id !== userId));
                    setStreaming(false);

                    return;
                }

                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();

                    if (done) {
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    buffer = buffer.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    for (const line of lines) {
                        appendSsePayload(line, (delta) => {
                            setMessages((prev) =>
                                prev.map((m) =>
                                    m.id === assistantId ? { ...m, content: m.content + delta } : m,
                                ),
                            );
                        });
                    }
                }

                if (buffer !== '') {
                    appendSsePayload(buffer.replace(/\r\n/g, '\n').replace(/\r/g, '\n'), (delta) => {
                        setMessages((prev) =>
                            prev.map((m) =>
                                m.id === assistantId ? { ...m, content: m.content + delta } : m,
                            ),
                        );
                    });
                }
            } catch (e) {
                setError(e instanceof Error ? e.message : 'Something went wrong.');
                setMessages((prev) => prev.filter((m) => m.id !== assistantId && m.id !== userId));
            } finally {
                setMessages((prev) =>
                    prev.map((m) => (m.id === assistantId ? { ...m, streaming: false } : m)),
                );

                if (effectiveTeamId !== null && threadId !== null) {
                    const syncTeamId = effectiveTeamId;
                    const syncThreadId = threadId;

                    void (async () => {
                        const fetchTail = async (): Promise<MessagesPageResponse | null> => {
                            const query: Record<string, number> = {
                                team_id: syncTeamId,
                                thread_id: syncThreadId,
                                per_page: FPL_CHAT_PER_PAGE,
                            };

                            if (maxDbIdBeforeSend > 0) {
                                query.after_id = maxDbIdBeforeSend;
                            }

                            const syncResponse = await fetch(fplChat.messages.index.url({ query }), {
                                credentials: 'same-origin',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!syncResponse.ok) {
                                return null;
                            }

                            return (await syncResponse.json()) as MessagesPageResponse;
                        };

                        try {
                            const maxAttempts = 14;

                            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                                await sleep(attempt === 0 ? 40 : 110);

                                if (
                                    threadIdRef.current !== syncThreadId ||
                                    effectiveTeamIdRef.current !== syncTeamId
                                ) {
                                    return;
                                }

                                const data = await fetchTail();

                                if (data === null) {
                                    continue;
                                }

                                const incomingRows = data.messages ?? [];
                                const incoming = mapServerMessages(incomingRows);
                                const hasAssistantInIncoming = incoming.some((m) => m.role === 'assistant');

                                const hasDbUserForThisTurn =
                                    maxDbIdBeforeSend === 0
                                        ? incoming.some((m) => m.role === 'user')
                                        : incoming.some((m) => {
                                              const n = chatDbNumericId(m.id);

                                              return n !== null && n > maxDbIdBeforeSend;
                                          });

                                const drops = new Set<string>();

                                if (hasDbUserForThisTurn) {
                                    drops.add(userId);
                                }

                                if (hasAssistantInIncoming) {
                                    drops.add(assistantId);
                                }

                                setMessages((prev) => {
                                    if (
                                        threadIdRef.current !== syncThreadId ||
                                        effectiveTeamIdRef.current !== syncTeamId
                                    ) {
                                        return prev;
                                    }

                                    return mergeMessagesWithIncoming(prev, incoming, drops);
                                });

                                if (
                                    threadIdRef.current === syncThreadId &&
                                    effectiveTeamIdRef.current === syncTeamId
                                ) {
                                    setHasMoreOlder(data.has_more_older);
                                }

                                if (hasAssistantInIncoming) {
                                    break;
                                }

                                if (attempt >= maxAttempts - 1) {
                                    break;
                                }
                            }
                        } catch {
                            //
                        }
                    })();
                }

                setStreaming(false);
                textareaRef.current?.focus();
            }
        },
        [appendSsePayload, csrfToken, effectiveTeamId, messages, profileTeamMismatch, streaming, threadId],
    );

    const onComposerKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const latest = e.currentTarget.value;
            void sendMessageWithText(latest);
        }
    };

    return (
        <>
            <Head title="FPL Assistant" />
            <div className="flex min-h-0 flex-1 flex-col">
                <div className="border-b border-border/60 bg-gradient-to-r from-violet-500/8 via-transparent to-cyan-500/8 px-4 py-3 md:px-6">
                    <div className="mx-auto flex max-w-3xl flex-col gap-2">
                        <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                            <Sparkles className="size-4 text-violet-600 dark:text-violet-400" aria-hidden />
                            <span>Fantasy Premier League assistant</span>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-xs text-muted-foreground leading-relaxed">
                                Answers use your squad context. Responses stream in real time as the model writes.
                            </p>
                            <div className="flex flex-wrap items-center justify-end gap-2">
                                {effectiveTeamId !== null &&
                                !profileTeamMismatch &&
                                threadId !== null &&
                                messages.length > 0 ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 shrink-0 text-xs text-muted-foreground"
                                        onClick={() => clearConversation()}
                                    >
                                        Clear history
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                        {effectiveTeamId !== null && !profileTeamMismatch ? (
                            <div className="flex flex-wrap items-center gap-2">
                                <label htmlFor="fpl-chat-thread" className="text-[11px] text-muted-foreground">
                                    Chat
                                </label>
                                <select
                                    id="fpl-chat-thread"
                                    className="h-8 min-w-[10rem] max-w-full flex-1 rounded-lg border border-input bg-background px-2 text-xs shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60 sm:max-w-xs"
                                    value={
                                        threadId !== null &&
                                        conversationsList.some((c) => c.id === threadId)
                                            ? String(threadId)
                                            : ''
                                    }
                                    disabled={
                                        loadingConvos || conversationsList.length === 0 || streaming || creatingChat
                                    }
                                    onChange={(e) => {
                                        const id = parseInt(e.target.value, 10);

                                        if (!Number.isFinite(id)) {
                                            return;
                                        }

                                        setThreadId(id);
                                    }}
                                >
                                    {conversationsList.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {formatThreadOptionLabel(c)}
                                        </option>
                                    ))}
                                </select>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs"
                                    disabled={
                                        streaming ||
                                        creatingChat ||
                                        loadingConvos ||
                                        effectiveTeamId === null
                                    }
                                    onClick={() => void createNewChat()}
                                >
                                    {creatingChat ? (
                                        <Loader2 className="size-3.5 animate-spin" aria-hidden />
                                    ) : (
                                        <MessageSquarePlus className="size-3.5" aria-hidden />
                                    )}
                                    New chat
                                </Button>
                                {loadingConvos ? (
                                    <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                                        <Loader2 className="size-3 animate-spin" aria-hidden />
                                        Loading…
                                    </span>
                                ) : null}
                            </div>
                        ) : null}
                        <Collapsible open={settingsOpen} onOpenChange={setSettingsOpen}>
                            <CollapsibleTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 w-fit gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <ChevronDown
                                        className={cn(
                                            'size-3.5 transition-transform',
                                            settingsOpen && 'rotate-180',
                                        )}
                                    />
                                    FPL team ID & profile
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="space-y-2 pt-1">
                                <div className="flex flex-col gap-2 rounded-xl border border-border/80 bg-card/80 p-3 shadow-sm backdrop-blur-sm sm:flex-row sm:items-end">
                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <Label htmlFor="fpl-team-id" className="text-xs">
                                            Entry (team) ID
                                        </Label>
                                        <Input
                                            id="fpl-team-id"
                                            type="number"
                                            min={1}
                                            inputMode="numeric"
                                            value={teamIdInput}
                                            onChange={(e) => {
                                                setTeamIdInput(e.target.value);
                                                setProfileBanner(null);
                                            }}
                                            placeholder="e.g. 1234567"
                                            disabled={streaming || savingProfile}
                                            className="h-9"
                                        />
                                        {profileFieldErrors.fpl_team_id ? (
                                            <p className="text-xs text-destructive">{profileFieldErrors.fpl_team_id}</p>
                                        ) : null}
                                        {profileBanner ? (
                                            <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                {profileBanner}
                                            </p>
                                        ) : null}
                                        {profileTeamMismatch ? (
                                            <p className="text-xs text-amber-700 dark:text-amber-400 leading-relaxed">
                                                Chat uses the team ID saved on your profile. Save this ID to your profile
                                                or match the field above to profile team {auth.user.fpl_team_id} to send
                                                messages.
                                            </p>
                                        ) : null}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        className="shrink-0"
                                        disabled={savingProfile || streaming}
                                        onClick={() => saveTeamIdToProfile()}
                                    >
                                        {savingProfile ? 'Saving…' : 'Save to profile'}
                                    </Button>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </div>

                <div className="relative flex min-h-0 flex-1 flex-col bg-gradient-to-b from-muted/25 to-background">
                    <div className="mx-auto flex w-full max-w-3xl min-h-0 flex-1 flex-col px-3 pb-3 pt-2 md:px-6 md:pb-4">
                        <div
                            ref={scrollAreaRef}
                            onScroll={onMessagesScroll}
                            className="min-h-0 flex-1 overflow-y-auto rounded-2xl border border-border/50 bg-card/30 px-2 py-4 shadow-inner backdrop-blur-sm md:px-4"
                        >
                            {loadingOlder ? (
                                <div className="mb-3 flex justify-center py-1">
                                    <span className="inline-flex items-center gap-2 text-[11px] text-muted-foreground">
                                        <Loader2 className="size-3 animate-spin" aria-hidden />
                                        Loading earlier messages…
                                    </span>
                                </div>
                            ) : null}
                            {loadingMessages && messages.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 px-4 py-16 text-center">
                                    <Loader2 className="size-8 animate-spin text-muted-foreground" aria-hidden />
                                    <p className="text-xs text-muted-foreground">Loading this chat…</p>
                                </div>
                            ) : messages.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-3 px-4 py-16 text-center">
                                    <div className="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500/15 to-cyan-500/15 ring-1 ring-border/60">
                                        <Bot className="size-7 text-muted-foreground" />
                                    </div>
                                    <div className="max-w-sm space-y-1">
                                        <p className="text-sm font-medium text-foreground">Start a conversation</p>
                                        <p className="text-xs text-muted-foreground leading-relaxed">
                                            Ask about captaincy, transfers, fixtures, or chips. Add your FPL team ID
                                            above if it is not on your profile yet.
                                        </p>
                                    </div>
                                    {promptSuggestions.length > 0 &&
                                    effectiveTeamId !== null &&
                                    !profileTeamMismatch &&
                                    threadId !== null ? (
                                        <div className="flex max-w-md flex-wrap justify-center gap-2 pt-2">
                                            {promptSuggestions.map((s) => (
                                                <button
                                                    key={s.id}
                                                    type="button"
                                                    className="rounded-full border border-border bg-background px-3 py-1.5 text-left text-xs text-foreground shadow-sm transition-colors hover:bg-muted/70 disabled:opacity-50"
                                                    disabled={streaming || creatingChat}
                                                    onClick={() => void sendMessageWithText(s.body)}
                                                >
                                                    {s.body}
                                                </button>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            ) : (
                                <ul className="flex flex-col gap-4" aria-live="polite">
                                    {messages.map((m) => (
                                        <li
                                            key={m.id}
                                            className={cn(
                                                'flex gap-3',
                                                m.role === 'user' ? 'flex-row-reverse' : 'flex-row',
                                            )}
                                        >
                                            <div
                                                className={cn(
                                                    'flex size-8 shrink-0 items-center justify-center rounded-full ring-1 ring-border/80',
                                                    m.role === 'user'
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-muted text-muted-foreground',
                                                )}
                                                aria-hidden
                                            >
                                                {m.role === 'user' ? (
                                                    <UserRound className="size-4" />
                                                ) : (
                                                    <Bot className="size-4" />
                                                )}
                                            </div>
                                            <div
                                                className={cn(
                                                    'max-w-[min(100%,28rem)] rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm',
                                                    m.role === 'user'
                                                        ? 'bg-primary text-primary-foreground rounded-tr-md'
                                                        : 'border border-border/80 bg-background/95 text-foreground rounded-tl-md',
                                                )}
                                            >
                                                {m.role === 'assistant' && m.streaming && m.content === '' ? (
                                                    <span className="inline-flex items-center gap-2 text-muted-foreground">
                                                        <Loader2 className="size-3.5 animate-spin" aria-hidden />
                                                        Thinking…
                                                    </span>
                                                ) : (
                                                    <span className="whitespace-pre-wrap break-words">{m.content}</span>
                                                )}
                                                {m.role === 'assistant' && m.streaming && m.content !== '' ? (
                                                    <span
                                                        className="ml-0.5 inline-block h-4 w-0.5 translate-y-0.5 animate-pulse bg-foreground/70 align-middle"
                                                        aria-hidden
                                                    />
                                                ) : null}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <div ref={scrollAnchorRef} className="h-px w-full shrink-0" aria-hidden />
                        </div>

                        {error ? (
                            <p
                                className="mt-2 rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive"
                                role="alert"
                            >
                                {error}
                            </p>
                        ) : null}

                        <div className="mt-3 shrink-0 rounded-2xl border border-border/80 bg-card p-2 shadow-lg">
                            {promptSuggestions.length > 0 &&
                            effectiveTeamId !== null &&
                            !profileTeamMismatch &&
                            threadId !== null &&
                            messages.length > 0 ? (
                                <div className="mb-2 flex flex-wrap gap-2 px-1">
                                    {promptSuggestions.map((s) => (
                                        <button
                                            key={s.id}
                                            type="button"
                                            className="rounded-full border border-border bg-background px-3 py-1 text-left text-[11px] text-foreground shadow-sm transition-colors hover:bg-muted/70 disabled:opacity-50"
                                            disabled={streaming || creatingChat}
                                            onClick={() => void sendMessageWithText(s.body)}
                                        >
                                            {s.body}
                                        </button>
                                    ))}
                                </div>
                            ) : null}
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <textarea
                                    ref={textareaRef}
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={onComposerKeyDown}
                                    rows={1}
                                    disabled={
                                        streaming ||
                                        effectiveTeamId === null ||
                                        profileTeamMismatch ||
                                        threadId === null ||
                                        loadingConvos
                                    }
                                    placeholder={
                                        effectiveTeamId === null
                                            ? 'Set your FPL team ID above to start…'
                                            : profileTeamMismatch
                                              ? 'Match or save profile team ID to send…'
                                              : loadingConvos || threadId === null
                                                ? 'Preparing chat…'
                                                : 'Message your FPL assistant…'
                                    }
                                    className="max-h-40 min-h-[44px] flex-1 resize-none rounded-xl border-0 bg-transparent px-3 py-2.5 text-sm outline-none ring-0 placeholder:text-muted-foreground focus-visible:ring-0 disabled:opacity-60"
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    className="size-10 shrink-0 rounded-xl shadow-md sm:mb-0.5"
                                    disabled={
                                        streaming ||
                                        !draft.trim() ||
                                        effectiveTeamId === null ||
                                        profileTeamMismatch ||
                                        threadId === null ||
                                        loadingConvos
                                    }
                                    onClick={() => {
                                        const latest =
                                            textareaRef.current?.value.trim() ?? draft.trim();
                                        void sendMessageWithText(latest);
                                    }}
                                    aria-label="Send message"
                                >
                                    {streaming ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <SendHorizontal className="size-4" />
                                    )}
                                </Button>
                            </div>
                            <p className="px-2 pb-1 text-[10px] text-muted-foreground">
                                Enter to send · Shift+Enter for new line
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

FplChat.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'FPL Assistant',
            href: fpl.chat(),
        },
    ],
};
