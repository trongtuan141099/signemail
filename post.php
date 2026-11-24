<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';

$slug = $_GET['slug'] ?? '';
$query = db()->prepare("SELECT * FROM posts WHERE slug=? AND status='published'");
$query->execute([is_string($slug) ? $slug : '']);
$post = $query->fetch();
if (!$post) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    page_header('Không tìm thấy bài viết · SignEmail', '', 'blog');
    ?>
    <main class="shell blog-page"><h1>Không tìm thấy bài viết</h1><p><a class="text-link" href="<?= h(linkto('blog.php')) ?>">Về trang blog</a></p></main>
    <?php page_footer();
    exit;
}

$canonical = post_url($post['slug']);
$cover = blog_cover($post);
$date = published_date($post['published_at']);
[$dom, $body] = document_fragment($post['content']);
$blocks = array_values(array_filter(iterator_to_array($body->childNodes), static fn(DOMNode $node): bool => $node instanceof DOMElement || trim($node->textContent) !== ''));
$toc = [];
$insertAd = max(1, (int) ceil(count($blocks) / 2));
foreach ($blocks as $index => $block) {
    if (!$block instanceof DOMElement || $block->tagName !== 'h2') continue;
    $label = trim($block->textContent);
    if ($label === '') continue;
    $id = 'muc-' . ($index + 1) . '-' . slugify($label);
    $block->setAttribute('id', $id);
    $toc[$id] = $label;
}
while (isset($blocks[$insertAd - 1]) && $blocks[$insertAd - 1] instanceof DOMElement && in_array($blocks[$insertAd - 1]->tagName, ['h2','h3','h4'], true)) {
    $insertAd++;
}

page_header($post['meta_title'] ?: $post['title'] . ' · SignEmail',
    $post['meta_description'] ?: ($post['excerpt'] ?: mb_substr(trim(strip_tags($post['content'])), 0, 160)),
    'blog', $canonical, $cover);
?>
<main class="shell blog-page article-page">
    <a class="text-link breadcrumbs" href="<?= h(linkto('blog.php')) ?>">Cẩm nang email</a>
    <header class="article-masthead">
        <div class="article-masthead-copy">
            <p class="eyebrow"><?= h($post['category'] ?: 'Hướng dẫn') ?></p>
            <h1 id="article-title"><?= h($post['title']) ?></h1>
            <?php if ($post['excerpt']): ?><p class="article-intro"><?= h($post['excerpt']) ?></p><?php endif; ?>
            <div class="article-byline"><span>SignEmail</span><time datetime="<?= h($date->format(DATE_ATOM)) ?>"><?= h($date->format('d/m/Y')) ?></time><span><?= reading_minutes($post['content']) ?> phút đọc</span></div>
        </div>
        <img class="article-cover" src="<?= h($cover) ?>" alt="<?= $post['cover'] ? h($post['title']) : '' ?>" fetchpriority="high">
    </header>
    <div class="article-layout">
        <aside class="article-rail">
            <?php if ($toc): ?>
            <nav class="article-toc" aria-label="Mục lục bài viết">
                <h2>Mục lục</h2>
                <ol><?php foreach ($toc as $id => $label): ?><li><a href="#<?= h($id) ?>"><?= h($label) ?></a></li><?php endforeach; ?></ol>
            </nav>
            <?php endif; ?>
            <?php editorial_banner('tool'); ?>
        </aside>
        <article class="article" aria-labelledby="article-title">
            <div class="article-content">
                <?php foreach ($blocks as $index => $block):
                    echo $dom->saveHTML($block);
                    if ($index + 1 === $insertAd) ad('article');
                endforeach; ?>
            </div>
            <div class="article-cta"><h2>Áp dụng vào chữ ký của bạn</h2><a class="button" href="<?= h(linkto('#editor')) ?>">Mở trình tạo chữ ký</a></div>
        </article>
    </div>
    <?php $related = related_posts($post); if ($related): ?>
    <section class="related"><div class="section-heading"><h2>Đọc tiếp</h2><a class="text-link" href="<?= h(linkto('blog.php')) ?>">Tất cả bài viết</a></div><?php post_cards($related); ?></section>
    <?php endif; ?>
</main>
<?php page_footer(); ?>
