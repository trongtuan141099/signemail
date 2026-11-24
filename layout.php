<?php
declare(strict_types=1);
require_once __DIR__ . '/app-server.php';

function page_header(string $title, string $description, string $active = '', string $canonical = '', string $image = '', string $activity = ''): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ?><!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <?php if ($canonical): ?>
    <link rel="canonical" href="<?= h(canonical_url($canonical)) ?>">
    <meta property="og:url" content="<?= h(canonical_url($canonical)) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <?php if ($image): ?><meta property="og:image" content="<?= h(canonical_url($image)) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= h(linkto('assets/site.css')) ?>">
    <link rel="stylesheet" href="<?= h(linkto('assets/studio.css')) ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="<?= h(linkto('assets/site.js')) ?>"></script>
</head>
<body data-activity-url="<?= h(linkto('activity.php')) ?>" data-activity-page="<?= h($activity) ?>">
<header class="site-header">
    <div class="shell site-nav">
        <a class="brand" href="<?= h(linkto()) ?>"><img class="brand-icon" src="<?= h(linkto('assets/sign-mark.png')) ?>" width="36" height="36" alt="">Sign<span>Email</span></a>
        <nav aria-label="Điều hướng chính">
            <a <?= $active === 'tool' ? 'aria-current="page"' : '' ?> href="<?= h(linkto()) ?>">Trình tạo</a>
            <a <?= $active === 'blog' ? 'aria-current="page"' : '' ?> href="<?= h(linkto('blog.php')) ?>">Cẩm nang</a>
        </nav>
        <a class="button button-small nav-action" href="<?= h(linkto('#editor')) ?>">Tạo chữ ký</a>
    </div>
</header>
    <?php ad('header');
}

function page_footer(): void
{
    ad('footer'); ?>
<footer class="site-footer">
    <div class="shell footer-inner">
        <a class="brand" href="<?= h(linkto()) ?>"><img class="brand-icon" src="<?= h(linkto('assets/sign-mark.png')) ?>" width="36" height="36" alt="">Sign<span>Email</span></a>
        <span>Trình tạo chữ ký email cho Gmail và Outlook.</span>
        <a href="<?= h(linkto('admin/')) ?>">Quản trị</a>
    </div>
</footer>
</body></html>
    <?php
}

function editorial_banner(string $kind): void
{
    $guide = $kind === 'guide';
    $path = $guide ? 'blog.php' : '#editor';
    ?>
    <aside class="editorial-banner <?= $guide ? 'banner-guide' : 'banner-tool' ?>">
        <div class="banner-text">
            <small>Từ SignEmail</small>
            <strong><?= $guide ? 'Cài chữ ký vào email' : 'Tạo chữ ký có dấu ấn riêng' ?></strong>
            <p><?= $guide ? 'Hướng dẫn từng bước cho Gmail và Outlook.' : 'Thêm logo và xem trước chữ ký của bạn.' ?></p>
            <a class="banner-cta" href="<?= h(linkto($path)) ?>"><?= $guide ? 'Đọc cẩm nang' : 'Mở trình tạo' ?></a>
        </div>
        <span class="banner-mark" aria-hidden="true"><?= $guide ? '✉' : '✦' ?></span>
    </aside>
    <?php
}

function post_cards(array $posts): void
{
    if (!$posts) {
        echo '<p class="empty">Chưa có bài viết được xuất bản.</p>';
        return;
    }
    echo '<div class="post-grid grid gap-5">';
    foreach ($posts as $post): ?>
        <article class="post-card" data-category="<?= h($post['category']) ?>">
            <a class="post-photo" tabindex="-1" aria-hidden="true" href="<?= h(post_url($post['slug'])) ?>"><img loading="lazy" src="<?= h(blog_cover($post)) ?>" alt=""></a>
            <div class="post-card-body">
                <div class="post-card-meta"><span><?= h($post['category'] ?: 'Hướng dẫn') ?></span><span><?= reading_minutes($post['content']) ?> phút đọc</span></div>
                <h3><a href="<?= h(post_url($post['slug'])) ?>"><?= h($post['title']) ?></a></h3>
                <?php if ($post['excerpt']): ?><p><?= h($post['excerpt']) ?></p><?php endif; ?>
                <a class="text-link" href="<?= h(post_url($post['slug'])) ?>" aria-label="Đọc bài <?= h($post['title']) ?>">Đọc bài viết</a>
            </div>
        </article>
    <?php endforeach;
    echo '</div>';
}
