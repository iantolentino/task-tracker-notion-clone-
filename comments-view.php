<?php
// Shared markup for a ticket's comment thread (admin inbox and the requester's My tickets page).

function render_ticket_comments(array $comments): void
{
    $esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    if (!$comments) {
        echo '<p class="comment-empty">No comments yet.</p>';
        return;
    }
    echo '<ol class="comment-list">';
    foreach ($comments as $comment) {
        $isAdmin = ($comment['author_type'] ?? '') !== 'requester';
        $time = stored_time($comment['created_at'] ?? '');
        ?>
        <li class="comment<?php echo $isAdmin ? ' is-admin' : ''; ?>">
            <div class="comment-head">
                <span class="comment-author"><?php echo $esc($comment['author_name'] ?: ($isAdmin ? 'Team' : 'Requester')); ?></span>
                <span class="comment-role"><?php echo $isAdmin ? 'Team' : 'Requester'; ?></span>
                <?php if ($time): ?><time datetime="<?php echo $esc(date('c', $time)); ?>"><?php echo $esc(date('M j, Y · g:i A', $time)); ?></time><?php endif; ?>
            </div>
            <p class="comment-body"><?php echo $esc($comment['body']); ?></p>
        </li>
        <?php
    }
    echo '</ol>';
}
