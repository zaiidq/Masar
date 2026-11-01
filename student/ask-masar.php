<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/ask-masar.php';

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: /masar/admin/dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function chatEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$sessions = listChatSessions($pdo, $userId);

$activeSession = null;
$messages = [];
$requestedSessionId = (int) ($_GET['session'] ?? 0);

if ($requestedSessionId > 0) {
    $activeSession = findChatSession($pdo, $requestedSessionId, $userId);

    if ($activeSession) {
        $messages = listChatMessages($pdo, (int) $activeSession['id']);
    }
}

/*
 * "Explain" from the Recommendations page: start a new conversation with
 * a ready question about that course (only for the student's own
 * accepted recommendations).
 */
$prefillQuestion = '';
$explainCode = trim((string) ($_GET['explain'] ?? ''));

if ($explainCode !== '' && $activeSession === null) {
    $explainStatement = $pdo->prepare(
        "SELECT rr.course_code, rr.course_name
         FROM record_recommendations rr
         JOIN academic_records ar ON ar.id = rr.record_id
         WHERE ar.user_id = :user_id
           AND ar.is_current = 1
           AND ar.status = 'analyzed'
           AND rr.is_accepted = 1
           AND rr.course_code = :course_code
         LIMIT 1"
    );
    $explainStatement->execute([
        'user_id' => $userId,
        'course_code' => $explainCode,
    ]);

    $explainCourse = $explainStatement->fetch();

    if ($explainCourse) {
        $prefillQuestion = sprintf(
            t('chat_explain_question'),
            $explainCourse['course_code'],
            $explainCourse['course_name']
        );
    }
}

$hasRecordStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM academic_records
     WHERE user_id = :user_id AND is_current = 1 AND status = 'analyzed'"
);
$hasRecordStatement->execute(['user_id' => $userId]);
$hasRecord = (int) $hasRecordStatement->fetchColumn() > 0;

$remainingToday = max(0, askMasarDailyLimit() - askMasarQuestionsToday($pdo, $userId));

$starterQuestions = [
    t('chat_starter_why'),
    t('chat_starter_hours'),
    t('chat_starter_unlock'),
    t('chat_starter_failed'),
];

$pageTitle = t('ask_masar');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content chat-page">

    <aside class="chat-sessions" aria-label="<?= chatEscape(t('chat_history')) ?>">
        <a href="/masar/student/ask-masar.php" class="chat-new-button">
            + <?= chatEscape(t('chat_new')) ?>
        </a>

        <details class="chat-sessions__details" <?= $sessions ? 'open' : '' ?>>
            <summary><?= chatEscape(t('chat_history')) ?></summary>

            <?php if (!$sessions): ?>
                <p class="chat-sessions__empty"><?= chatEscape(t('chat_history_empty')) ?></p>
            <?php else: ?>
                <ul class="chat-sessions__list" data-session-list>
                    <?php foreach ($sessions as $session): ?>
                        <li class="<?= $activeSession && (int) $activeSession['id'] === (int) $session['id'] ? 'is-active' : '' ?>">
                            <a href="/masar/student/ask-masar.php?session=<?= (int) $session['id'] ?>" dir="auto">
                                <?= chatEscape($session['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </details>
    </aside>

    <section class="chat-panel">
        <header class="chat-panel__header">
            <div>
                <span class="path-eyebrow">
                    <span class="path-eyebrow__tick"></span>
                    <?= chatEscape(t('ask_masar')) ?>
                </span>

                <h1 dir="auto">
                    <?= chatEscape($activeSession ? $activeSession['title'] : t('chat_headline')) ?>
                </h1>
            </div>

            <?php if ($activeSession): ?>
                <button
                    type="button"
                    class="chat-delete-button"
                    data-delete-session="<?= (int) $activeSession['id'] ?>"
                >
                    <?= chatEscape(t('chat_delete')) ?>
                </button>
            <?php endif; ?>
        </header>

        <?php if (!$hasRecord): ?>
            <div class="path-note path-note--attention chat-no-record">
                <p class="path-note__body"><?= chatEscape(t('chat_no_record')) ?></p>
                <a class="path-note__link" href="/masar/student/academic-record.php">
                    <?= chatEscape(t('path_upload_record')) ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="chat-messages" data-chat-messages aria-live="polite">
            <?php if (!$messages): ?>
                <div class="chat-welcome" data-chat-welcome>
                    <img src="/masar/assets/brand/masar-mark.svg" alt="" class="chat-welcome__mark">
                    <p><?= chatEscape(t('chat_welcome')) ?></p>

                    <div class="chat-starters">
                        <?php foreach ($starterQuestions as $starter): ?>
                            <button type="button" class="chat-starter" data-starter>
                                <?= chatEscape($starter) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="chat-message chat-message--<?= $message['role'] === 'assistant' ? 'assistant' : 'user' ?>">
                        <div class="chat-bubble">
                            <?php if ($message['role'] === 'assistant'): ?>
                                <?= renderChatText($message['content']) ?>
                            <?php else: ?>
                                <p dir="auto"><?= nl2br(chatEscape($message['content'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form class="chat-form" data-chat-form>
            <input type="hidden" name="csrf_token" value="<?= chatEscape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="send">
            <input
                type="hidden"
                name="session_id"
                value="<?= $activeSession ? (int) $activeSession['id'] : 0 ?>"
                data-session-id
            >

            <label for="chat-input" class="visually-hidden"><?= chatEscape(t('chat_placeholder')) ?></label>
            <textarea
                id="chat-input"
                name="message"
                rows="2"
                maxlength="<?= ASK_MASAR_MAX_MESSAGE_LENGTH ?>"
                placeholder="<?= chatEscape(t('chat_placeholder')) ?>"
                dir="auto"
                data-chat-input
                <?= $prefillQuestion !== '' ? 'data-autosend' : '' ?>
            ><?= chatEscape($prefillQuestion) ?></textarea>

            <button type="submit" class="path-button chat-send" data-chat-send>
                <?= chatEscape(t('chat_send')) ?>
            </button>
        </form>

        <p class="chat-footnote">
            <span data-remaining-today><?= chatEscape(sprintf(t('chat_remaining_today'), $remainingToday)) ?></span>
            · <?= chatEscape(t('chat_disclaimer')) ?>
        </p>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-chat-form]');
    const input = document.querySelector('[data-chat-input]');
    const sendButton = document.querySelector('[data-chat-send]');
    const messagesBox = document.querySelector('[data-chat-messages]');
    const sessionField = document.querySelector('[data-session-id]');
    const remainingLabel = document.querySelector('[data-remaining-today]');

    const text = {
        thinking: <?= json_encode(t('chat_thinking'), JSON_UNESCAPED_UNICODE) ?>,
        error: <?= json_encode(t('chat_error_unavailable'), JSON_UNESCAPED_UNICODE) ?>,
        empty: <?= json_encode(t('chat_error_empty'), JSON_UNESCAPED_UNICODE) ?>,
        remaining: <?= json_encode(t('chat_remaining_today'), JSON_UNESCAPED_UNICODE) ?>,
        confirmDelete: <?= json_encode(t('chat_delete_confirm'), JSON_UNESCAPED_UNICODE) ?>
    };

    function scrollToEnd() {
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function addMessage(role, content, isHtml) {
        const welcome = messagesBox.querySelector('[data-chat-welcome]');

        if (welcome) {
            welcome.remove();
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'chat-message chat-message--' + role;

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';

        if (isHtml) {
            // reply_html is escaped and rendered on the server.
            bubble.innerHTML = content;
        } else {
            const paragraph = document.createElement('p');
            paragraph.dir = 'auto';
            paragraph.textContent = content;
            bubble.appendChild(paragraph);
        }

        wrapper.appendChild(bubble);
        messagesBox.appendChild(wrapper);
        scrollToEnd();

        return wrapper;
    }

    async function send() {
        const question = input.value.trim();

        if (question === '') {
            input.focus();
            input.setAttribute('aria-invalid', 'true');
            input.placeholder = text.empty;
            return;
        }

        input.removeAttribute('aria-invalid');
        addMessage('user', question, false);

        const pending = addMessage('assistant', text.thinking, false);
        pending.classList.add('is-pending');

        const data = new FormData(form);
        input.value = '';
        sendButton.disabled = true;
        input.disabled = true;

        try {
            const response = await fetch('/masar/student/ask-masar-api.php', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            });

            const result = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || !result.success) {
                throw new Error(result.message || text.error);
            }

            pending.remove();
            addMessage('assistant', result.reply_html, true);

            if (sessionField.value === '0') {
                sessionField.value = String(result.session_id);
                history.replaceState(null, '', '/masar/student/ask-masar.php?session=' + result.session_id);
            }

            if (remainingLabel && typeof result.remaining_today === 'number') {
                remainingLabel.textContent = text.remaining.replace('%d', result.remaining_today);
            }
        } catch (error) {
            pending.remove();
            const failed = addMessage('assistant', error.message || text.error, false);
            failed.classList.add('is-error');
            input.value = question;
        } finally {
            sendButton.disabled = false;
            input.disabled = false;
            input.focus();
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        send();
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            send();
        }
    });

    document.querySelectorAll('[data-starter]').forEach(function (button) {
        button.addEventListener('click', function () {
            input.value = button.textContent.trim();
            send();
        });
    });

    const deleteButton = document.querySelector('[data-delete-session]');

    if (deleteButton) {
        deleteButton.addEventListener('click', async function () {
            if (!window.confirm(text.confirmDelete)) {
                return;
            }

            const data = new FormData();
            data.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
            data.append('action', 'delete_session');
            data.append('session_id', deleteButton.dataset.deleteSession);

            const response = await fetch('/masar/student/ask-masar-api.php', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            });

            if (response.ok) {
                window.location.href = '/masar/student/ask-masar.php';
            }
        });
    }

    scrollToEnd();

    if (input.hasAttribute('data-autosend') && input.value.trim() !== '') {
        history.replaceState(null, '', '/masar/student/ask-masar.php');
        send();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
