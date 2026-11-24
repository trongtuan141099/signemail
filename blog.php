<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';

$category = $_GET['topic'] ?? '';
$category = is_string($category) && in_array($category, blog_categories(), true) ? $category : '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$library = published_posts();
$available = array_column($library, 'category');
$topics = array_filter(blog_categories(), static fn(string $topic): bool => $topic === $category || in_array($topic, $available, true));
$posts = array_values(array_filter($library, static function (array $post) use ($category, $search): bool {
    if ($category !== '' && $post['category'] !== $category) return false;
    if ($search === '') return true;
    $haystack = slugify(mb_substr($post['title'] . ' ' . $post['excerpt'], 0, 120)) . '-' . slugify($post['excerpt']) . '-' . $post['slug'];
    if (!preg_match('/[\p{L}\p{N}]/u', $search)) return false;
    foreach (explode('-', slugify($search)) as $term) {
        if (!str_contains($haystack, $term)) return false;
    }
    return true;
}));
$featured = !$category && !$search && $posts ? array_shift($posts) : null;
$canonical = linkto('blog.php') . ($category ? '?' . http_build_query(['topic'=>$category]) : '');
page_header('Cẩm nang email · SignEmail', 'Hướng dẫn Microsoft Outlook, Gmail và thiết kế chữ ký email cho công việc.', 'blog', $canonical, '', 'blog');
?>
<main class="shell blog-page magazine">
    <header class="magazine-heading">
        <p class="eyebrow">Blog SignEmail</p>
        <h1>Cẩm nang email</h1>
        <p>Quản lý hộp thư, làm việc hiệu quả với Gmail và Outlook, và thiết kế chữ ký chuyên nghiệp.</p>
    </header>
    <?php if ($featured): ?>
        <article class="blog-lead">
            <a class="blog-lead-photo" href="<?= h(post_url($featured['slug'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= h(blog_cover($featured)) ?>" alt="" fetchpriority="high"></a>
            <div class="blog-lead-copy">
                <p class="eyebrow">Bài nổi bật · <?= h($featured['category'] ?: 'Hướng dẫn') ?></p>
                <h2><a href="<?= h(post_url($featured['slug'])) ?>"><?= h($featured['title']) ?></a></h2>
                <p><?= h($featured['excerpt']) ?></p>
                <div class="blog-lead-actions"><a class="button" href="<?= h(post_url($featured['slug'])) ?>">Đọc hướng dẫn</a><span><?= reading_minutes($featured['content']) ?> phút đọc</span></div>
            </div>
        </article>
    <?php endif; ?>
    <section class="editorial-feed" aria-labelledby="all-guides">
        <div class="section-heading"><h2 id="all-guides"><?= h($category ?: 'Bài viết mới') ?></h2><span><?= count($posts) ?> bài viết</span></div>
        <div class="feed-filters">
            <nav class="topics" aria-label="Chủ đề blog">
                <?php foreach (array_merge([''], $topics) as $topic):
                    $params = array_filter(['topic' => $topic, 'q' => $search], static fn(string $value): bool => $value !== '');
                ?>
                <a <?= $topic === $category ? 'aria-current="page"' : '' ?> href="<?= h(linkto('blog.php') . ($params ? '?' . http_build_query($params) : '') . '#all-guides') ?>"><?= h($topic ?: 'Tất cả') ?></a>
                <?php endforeach; ?>
            </nav>
            <form class="feed-search" action="<?= h(linkto('blog.php') . '#all-guides') ?>">
                <?php if ($category): ?><input type="hidden" name="topic" value="<?= h($category) ?>"><?php endif; ?>
                <label class="sr-only" for="blog-search">Tìm bài viết</label><input id="blog-search" type="search" name="q" value="<?= h($search) ?>" maxlength="100" placeholder="Tìm bài viết">
                <button class="button button-small">Tìm</button>
            </form>
        </div>
        <?php if ($posts): post_cards($posts); else: ?>
            <p class="empty">Không tìm thấy bài viết phù hợp. <a class="text-link" href="<?= h(linkto('blog.php') . '#all-guides') ?>">Xem tất cả bài viết</a></p>
        <?php endif; ?>
    </section>
</main>
<?php page_f