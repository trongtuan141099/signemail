<?php
declare(strict_types=1);

function db(): PDO
{
    static $db;
    if ($db instanceof PDO) {
        return $db;
    }

    $file = getenv('SIGNEMAIL_DB') ?: __DIR__ . '/storage/signemail.sqlite';
    $connection = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $connection->exec('PRAGMA busy_timeout = 5000; PRAGMA foreign_keys = ON');
    if ((int) $connection->query('PRAGMA user_version')->fetchColumn() < 8) {
        immediate_write($connection, static function () use ($connection): void {
            $version = (int) $connection->query('PRAGMA user_version')->fetchColumn();
            if ($version >= 8) return;
            $connection->exec(file_get_contents(__DIR__ . '/database.sql'));
            add_release_columns($connection);

            if ($version < 1) {
                install_templates($connection, 'templates-batch-01.json');
                install_posts($connection, 'posts.json');
            }
            if ($version < 2) {
                install_templates($connection, 'templates-batch-02.json');
                install_posts($connection, 'posts-release-02.json');
                $topic = $connection->prepare("UPDATE posts SET category=? WHERE slug=? AND category=''");
                $topic->execute(['Gmail', 'them-chu-ky-email-vao-gmail']);
                $topic->execute(['Outlook', 'cai-chu-ky-trong-outlook']);
                $topic->execute(['Chữ ký email', 'kiem-tra-anh-va-gif-trong-chu-ky-email']);
            }
            if ($version < 4) refresh_guides($connection);
            if ($version < 6) upgrade_guide_references($connection);
            if ($version === 6) {
                $source = $connection->prepare('UPDATE posts SET content = replace(content, ?, ?) WHERE slug = ? AND instr(content, ?) > 0');
                $previous = 'https://blog.google/products-and-platforms/products/workspace/gmail-tips-that-help-you-save-time-and-get-more-done/';
                $current = guide_references()['hen-gio-gui-va-hoan-tac-gmail']['figures'][0]['source'];
                $source->execute([$previous, $current, 'hen-gio-gui-va-hoan-tac-gmail', $previous]);
            }
            install_outlook_guides($connection);
            $connection->exec('PRAGMA user_version = 8');
        });
    }
    return $db = $connection;
}

function add_release_columns(PDO $db): void
{
    $columns = [
        'templates' => ['accent' => '#18634f', 'motion' => 'dots'],
        'posts' => ['category' => '', 'art' => ''],
        'ads' => ['headline' => '', 'description' => '', 'button_text' => '', 'tone' => 'forest', 'gallery' => '[]', 'transition' => 'fade'],
    ];
    foreach ($columns as $table => $fields) {
        $existing = array_column($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
        foreach ($fields as $column => $default) {
            if (!in_array($column, $existing, true)) {
                $db->exec("ALTER TABLE $table ADD COLUMN $column TEXT NOT NULL DEFAULT " . $db->quote($default));
            }
        }
    }
}

function install_templates(PDO $db, string $file): void
{
    $items = json_decode(file_get_contents(__DIR__ . '/data/' . $file), true, 64, JSON_THROW_ON_ERROR);
    $exists = $db->prepare('SELECT 1 FROM templates WHERE name=? AND style=? AND role=? LIMIT 1');
    foreach ($items as $item) {
        $exists->execute([$item['name'], $item['style'], $item['role']]);
        if (!$exists->fetchColumn()) save_template($db, template_data($item));
    }
}

function install_outlook_guides(PDO $db): void
{
    $files = glob(__DIR__ . '/data/outlook-25/*.html');
    if (!$files || count($files) !== 25) throw new RuntimeException('Thiếu bài trong bộ 25 hướng dẫn Outlook.');
    $items = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        [, $body] = document_fragment($content);
        $metadata = [];
        foreach (file(substr($file, 0, -5) . '.seo.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            [$key, $value] = explode(': ', $line, 2);
            $metadata[$key] = trim($value);
        }
        $items[] = ['title' => $body->getElementsByTagName('h1')->item(0)->textContent,
            'slug' => $metadata['Slug'], 'meta_title' => $metadata['Meta Title'],
            'excerpt' => $metadata['Meta Description'], 'meta_description' => $metadata['Meta Description'],
            'category' => 'Outlook', 'art' => 'outlook', 'content' => $content];
    }
    publish_seed_posts($db, $items);
}

function install_posts(PDO $db, string $file): void
{
    $items = json_decode(file_get_contents(__DIR__ . '/data/' . $file), true, 64, JSON_THROW_ON_ERROR);
    publish_seed_posts($db, $items);
}

function publish_seed_posts(PDO $db, array $items): void
{
    $publish = $db->prepare("INSERT OR IGNORE INTO posts(title,slug,excerpt,content,meta_title,meta_description,category,art,status,published_at)
        VALUES (:title,:slug,:excerpt,:content,:meta_title,:meta_description,:category,:art,'published',CURRENT_TIMESTAMP)");
    foreach (array_reverse($items) as $post) {
        $post['content'] = clean_fragment($post['content'], false);
        $publish->execute($post + ['category' => '', 'art' => '']);
    }
}

function refresh_guides(PDO $db): void
{
    $read = static fn(string $filename): array => json_decode(file_get_contents(__DIR__ . '/data/' . $filename), true, 64, JSON_THROW_ON_ERROR);
    $original = array_column(array_merge($read('posts.json'), $read('posts-release-02.json')), 'content', 'slug');
    $update = $db->prepare('UPDATE posts SET content=:content,updated_at=CURRENT_TIMESTAMP WHERE slug=:slug AND content=:original');
    foreach ($read('guide-editions.json') as $guide) {
        $update->execute(['content'=>clean_fragment(guide_html($guide), false), 'slug'=>$guide['slug'],
            'original'=>clean_fragment($original[$guide['slug']], false)]);
    }
}

function guide_references(): array
{
    static $references;
    return $references ??= json_decode(file_get_contents(__DIR__ . '/data/guide-reference.json'), true, 64, JSON_THROW_ON_ERROR);
}

function upgrade_guide_references(PDO $db): void
{
    $guides = json_decode(file_get_contents(__DIR__ . '/data/guide-editions.json'), true, 64, JSON_THROW_ON_ERROR);
    $update = $db->prepare('UPDATE posts SET content=:content,updated_at=CURRENT_TIMESTAMP WHERE slug=:slug AND content=:original');
    $references = guide_references();
    foreach ($guides as $guide) {
        $update->execute(['content'=>clean_fragment(guide_html($guide, $references[$guide['slug']]), false), 'slug'=>$guide['slug'],
            'original'=>clean_fragment(guide_html($guide), false)]);
    }
}

function guide_html(array $guide, ?array $reference = null): string
{
    $html = '<p>' . h($guide['intro']) . '</p>';
    $figure = 0;
    foreach ($guide['sections'] as $section) {
        $html .= '<h2>' . h($section['title']) . '</h2><p>' . h($section['detail']) . '</p>';
        if ($section['steps']) {
            $html .= '<ol>';
            foreach ($section['steps'] as $step) $html .= '<li>' . h($step) . '</li>';
            $html .= '</ol>';
        }
        if (empty($section['visual'])) continue;
        $figure++;
        if ($reference === null) {
            $src = 'assets/guides/' . $guide['slug'] . '-' . $figure . '.webp';
            $alt = implode(' → ', $section['visual']);
            $desc = 'Minh họa thao tác trên ' . $guide['app'] . ' · ' . $section['title'];
            $html .= '<figure><img src="' . h($src) . '" alt="' . h($alt) . '"><figcaption>' . h($desc) . '</figcaption></figure>';
        } elseif ($illustration = ($reference['figures'][$figure - 1] ?? null)) {
            $caption = h($illustration['caption']);
            $html .= '<figure><img src="' . h($illustration['file']) . '" alt="' . $caption . '"><figcaption>' . $caption . '</figcaption></figure>';
            if ($illustration['source']) {
                $credit = $illustration['by'] === 'Microsoft' ? 'Microsoft Support · Used with permission from Microsoft.' : 'Nguồn ảnh: Google.';
                $html .= '<p><small>' . h($credit) . ' <a href="' . h($illustration['source']) . '">Xem bài nguồn</a></small></p>';
            }
        }
    }
    $html .= '<h2>Ví dụ áp dụng</h2><p>' . h($guide['caseText']) . '</p>';
    foreach ($reference['notes'] ?? [] as $note) {
        $html .= '<h2>' . h($note['title']) . '</h2><p>' . h($note['detail']) . '</p>';
        if ($note['list']) {
            $html .= '<ul>';
            foreach ($note['list'] as $item) $html .= '<li>' . h($item) . '</li>';
            $html .= '</ul>';
        }
    }
    if ($guide['sources']) {
        $html .= '<h2>Tài liệu chính thức</h2><ul>';
        foreach ($guide['sources'] as [$label,$url]) $html .= '<li><a href="' . h($url) . '">' . h($label) . '</a></li>';
        $html .= '</ul>';
    }
    return $html;
}

function guide_cover_credit(array $post): ?string
{
    if ($post['cover']) return null;
    $photo = guide_references()[$post['slug']]['figures'][0] ?? null;
    if (!$photo || !$photo['source']) return null;
    return $photo['by'] === 'Microsoft' ? 'Microsoft Support · Used with permission from Microsoft.' : 'Ảnh giao diện: Google.';
}

function editorial_image(string $src): bool
{
    return (bool) preg_match('~^assets/guides/[a-z0-9-]+-[1-9][0-9]*\.webp$~D', $src);
}

function h(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function field(array $input, string $key, int $max, bool $required = false): string
{
    $raw = $input[$key] ?? '';
    if (!is_string($raw)) {
        throw new InvalidArgumentException('Giá trị trường ' . $key . ' không hợp lệ.');
    }
    $value = trim($raw);
    if (($required && $value === '') || mb_strlen($value) > $max) {
        throw new InvalidArgumentException('Kiểm tra trường ' . $key . ' (tối đa ' . $max . ' ký tự).');
    }
    return $value;
}

function web_url(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
        && parse_url($url, PHP_URL_USER) === null;
}

function linkto(string $path = ''): string
{
    $base = getenv('SIGNEMAIL_BASE_PATH');
    if ($base === false) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = preg_replace('~/(?:admin/)?[^/]*$~', '', $script);
        if (preg_match('~/blog/[^/]+/?$~', $script)) {
            $base = preg_replace('~/blog/[^/]+/?$~', '', $script);
        }
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return linkto($path) . '?v=' . filemtime(__DIR__ . '/' . $path);
}

function post_url(string $slug): string
{
    return linkto(getenv('SIGNEMAIL_PRETTY_URLS') === '0' ? 'post.php?slug=' . rawurlencode($slug) : 'blog/' . rawurlencode($slug));
}

function canonical_url(string $path): string
{
    $origin = rtrim(getenv('SIGNEMAIL_ORIGIN') ?: '', '/');
    return web_url($origin) ? $origin . $path : $path;
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

function brand_upload_limit(): int
{
    $limit = 10 * 1024 * 1024;
    foreach (['upload_max_filesize'=>0,'post_max_size'=>65536] as $setting => $overhead) {
        $value = trim((string) ini_get($setting));
        if (!preg_match('/^(\d+)\s*([kmg]?)$/iD', $value, $parts) || (int) $parts[1] === 0) continue;
        $bytes = (int) $parts[1] * ([''=>1,'k'=>1024,'m'=>1048576,'g'=>1073741824][strtolower($parts[2])]);
        $limit = (int) min($limit, max(1, $bytes - $overhead));
    }
    return $limit;
}

function upload_image(string $field): string
{
    return store_uploaded_image($_FILES[$field] ?? null);
}

function store_uploaded_image(?array $file): string
{
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Ảnh tải lên phải nhỏ hơn hoặc bằng 2 MB.');
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $image = @getimagesize($file['tmp_name']);
    if (!$image || !isset($types[$type]) || $type !== $image['mime'] || max($image[0], $image[1]) > 5000) {
        throw new InvalidArgumentException('Chọn ảnh PNG, JPG, GIF hoặc WebP có mỗi chiều tối đa 5.000 px.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$type];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $name)) {
        throw new RuntimeException('Không thể lưu ảnh tải lên.');
    }
    return $name;
}

function remove_upload(string $name): void
{
    if (preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/D', $name)) {
        $path = __DIR__ . '/uploads/' . $name;
        if (is_file($path)) @unlink($path);
    }
}

function immediate_write(PDO $db, callable $write): mixed
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $write();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function document_fragment(string $html): array
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    return [$dom, $dom->getElementsByTagName('body')->item(0)];
}

function signature_styles(string $source): string
{
    $colors = '(?:#[0-9a-f]{3}(?:[0-9a-f]{3})?|\{\{(?:primary_color|contrast_color)\}\}|transparent|white|black)';
    $length = '(?:0|\d{1,3}(?:\.\d+)?(?:px|pt|em|%))';
    $lengths = $length . '(?: +' . $length . '){0,3}';
    $rules = [
        'color' => $colors, 'background-color' => $colors,
        'font-family' => '[a-z0-9 ,\'"-]+|\{\{font_family\}\}',
        'font-size' => $length . '|\{\{(?:font_size|name_size)\}\}(?:px)?',
        'line-height' => $length . '|\d(?:\.\d+)?',
        'font-weight' => 'normal|bold|[1-9]00', 'font-style' => 'normal|italic',
        'padding' => $lengths, 'margin' => $lengths,
        'width' => $length . '|\{\{logo_width\}\}px', 'height' => $length . '|auto', 'max-width' => $length,
        'border-radius' => $lengths . '|\{\{avatar_radius\}\}(?:px)?',
        'border-collapse' => 'collapse|separate', 'border-spacing' => $lengths,
        'text-decoration' => 'none|underline', 'vertical-align' => 'top|middle|bottom|baseline',
        'text-align' => 'left|center|right', 'display' => 'block|inline|inline-block',
        'letter-spacing' => $length, 'white-space' => 'normal|nowrap',
        'overflow-wrap' => 'anywhere|break-word', 'word-break' => 'break-word|break-all|normal',
    ];
    foreach (['top','right','bottom','left'] as $side) {
        $rules['padding-' . $side] = $length;
        $rules['margin-' . $side] = $length;
        $rules['border-' . $side] = 'none|0|' . $length . ' (?:solid|dashed|dotted) ' . $colors;
    }
    $rules['border'] = $rules['border-top'];
    $safe = [];
    foreach (explode(';', $source) as $pair) {
        [$property, $value] = array_pad(explode(':', $pair, 2), 2, '');
        $property = strtolower(trim($property));
        $value = trim($value);
        if (isset($rules[$property]) && preg_match('~^(?:' . $rules[$property] . ')$~iD', $value)) {
            $safe[] = $property . ':' . $value;
        }
    }
    return implode(';', $safe);
}

function clean_fragment(string $html, bool $signature): string
{
    [$dom, $body] = document_fragment($html);
    $tags = explode(' ', $signature
        ? 'table tbody thead tfoot tr td th img a span strong b em i br p div'
        : 'p div br h1 h2 h3 h4 strong b em i u s ul ol li blockquote pre code a img hr small figure figcaption');
    $discard = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'textarea'];
    $walk = function (DOMNode $parent) use (&$walk, $tags, $discard, $signature): void {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMComment) {
                $parent->removeChild($node);
            }
            if (!$node instanceof DOMElement) {
                continue;
            }
            $tag = $node->tagName;
            if (!$signature && $tag === 'svg') {
                static $guideDiagrams;
                $guideDiagrams ??= array_flip(file(__DIR__ . '/data/outlook-diagrams.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                if (isset($guideDiagrams[hash('sha256', $node->C14N())])) continue;
            }
            if (in_array($tag, $discard, true)) {
                $parent->removeChild($node);
                continue;
            }
            $walk($node);
            if (!in_array($tag, $tags, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attr) {
                $key = $attr->name;
                $value = $attr->value;
                $safe = match ($key) {
                    'href' => $tag === 'a' && (web_url($value)
                        || preg_match('~^(?:mailto:[a-z0-9._%+\-]+@[a-z0-9.\-]+|tel:\+?[0-9(). \-]+)$~iD', $value)
                        || ($signature && preg_match('~^(?:(?:mailto:|tel:)?\{\{(?:email|phone|phone_href|website|cta_url|banner_link)\}\})$~D', $value))),
                    'src' => $tag === 'img' && (web_url($value) || (!$signature && editorial_image($value))
                        || ($signature && preg_match('~^\{\{(?:avatar|avatar_url|logo_url|banner_url|motion_url)\}\}$~D', $value))),
                    'alt' => $tag === 'img',
                    'style' => $signature,
                    'width' => $signature && preg_match('~^(?:\d{1,3}%?|\{\{logo_width\}\})$~D', $value),
                    'height', 'cellpadding', 'cellspacing', 'border', 'colspan', 'rowspan'
                        => $signature && preg_match('~^\d{1,3}%?$~D', $value),
                    'role' => $signature && $tag === 'table' && $value === 'presentation',
                    'align' => $signature && in_array($value, ['left','center','right'], true),
                    'valign' => $signature && in_array($value, ['top','middle','bottom'], true),
                    'bgcolor' => $signature && preg_match('~^(?:#[0-9a-f]{6}|\{\{primary_color\}\})$~iD', $value),
                    'data-if' => $signature && preg_match('~^(?:full_name|job_title|company|phone|email|website|address|avatar_url|logo_url|social_icons|banner_url|cta_url|banner_link|motion_url)$~D', $value),
                    'data-list' => !$signature && $tag === 'li' && in_array($value, ['bullet','ordered'], true),
                    default => false,
                };
                if (!$safe) {
                    $node->removeAttribute($key);
                } elseif ($key === 'style') {
                    $node->setAttribute('style', signature_styles($value));
                }
            }
            if ($tag === 'a' && !$signature) {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    };
    $walk($body);
    if ($signature) {
        $roots = array_values(array_filter(iterator_to_array($body->childNodes),
            static fn(DOMNode $node): bool => trim($node->textContent) !== '' || $node instanceof DOMElement));
        if (count($roots) !== 1 || !$roots[0] instanceof DOMElement || $roots[0]->tagName !== 'table') {
            throw new InvalidArgumentException('Mỗi mẫu chữ ký phải có một thẻ table bao ngoài.');
        }
    }
    $output = '';
    foreach ($body->childNodes as $node) {
        $output .= $dom->saveHTML($node);
    }
    if ($signature) {
        // libxml encodes braces in URI attributes when serializing HTML.
        $output = preg_replace('~(\b(?:href|src)="(?:mailto:|tel:)?)%7B%7B([a-z_]+)%7D%7D"~i', '$1{{$2}}"', $output);
    }
    return trim($output);
}

function wide_motion_assets(): array
{
    return ['plane'=>'Máy bay', 'ship'=>'Tàu thủy', 'cat'=>'Mèo mini', 'dog'=>'Cún mini',
        'rocket'=>'Tên lửa', 'train'=>'Tàu hỏa', 'fish'=>'Cá nhỏ', 'scooter'=>'Xe tay ga'];
}

function motion_assets(): array
{
    return ['dots'=>'Ba nhịp', 'spark'=>'Tia sáng', 'orbit'=>'Quỹ đạo', 'mail'=>'Phong bì', 'arrow'=>'Mũi tên', 'wave'=>'Dải sóng'] + wide_motion_assets();
}

function template_data(array $input): array
{
    return [
        'name' => field($input, 'name', 120, true),
        'style' => field($input, 'style', 70, true),
        'role' => field($input, 'role', 90, true),
        'html' => clean_fragment(field($input, 'html', 25000, true), true),
        'animated' => (int) in_array($input['animated'] ?? 0, [1, '1', true], true),
        'visible' => (int) in_array($input['visible'] ?? 1, [1, '1', true], true),
        'accent' => preg_match('/^#[0-9a-f]{6}$/iD', field($input, 'accent', 7)) ? strtolower($input['accent']) : '#18634f',
        'motion' => array_key_exists(field($input, 'motion', 15), motion_assets()) ? $input['motion'] : 'dots',
    ];
}

function template_record(array $row): array
{
    $row['animated'] = (bool) $row['animated'];
    if (isset($row['visible'])) $row['visible'] = (bool) $row['visible'];
    if (isset($row['id'])) $row['id'] = (int) $row['id'];
    return $row;
}

function save_template(PDO $db, array $data, int $id = 0): void
{
    if ($id) {
        $db->prepare('UPDATE templates SET name=:name,style=:style,role=:role,html=:html,animated=:animated,visible=:visible,accent=:accent,motion=:motion WHERE id=:id')
            ->execute($data + ['id' => $id]);
    } else {
        $db->prepare('INSERT INTO templates (name,style,role,html,animated,visible,accent,motion) VALUES (:name,:style,:role,:html,:animated,:visible,:accent,:motion)')
            ->execute($data);
    }
}

function slugify(string $text): string
{
    $text = str_replace(['Đ','đ'], ['D','d'], $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: '';
    return trim(substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), 0, 120), '-') ?: 'bai-viet';
}

function published_posts(int $limit = 0, int $exclude = 0): array
{
    $query = db()->prepare('SELECT * FROM posts WHERE status = :status AND id != :id ORDER BY published_at DESC,id DESC LIMIT :limit');
    $query->bindValue(':status', 'published');
    $query->bindValue(':id', $exclude, PDO::PARAM_INT);
    $query->bindValue(':limit', $limit ?: -1, PDO::PARAM_INT);
    $query->execute();
    return $query->fetchAll();
}

function blog_categories(): array
{
    return ['Outlook', 'Gmail', 'Chữ ký email', 'Năng suất'];
}

function blog_cover(array $post): string
{
    if ($post['cover']) return linkto('uploads/' . $post['cover']);
    $reference = guide_references()[$post['slug']]['figures'][0]['file'] ?? '';
    if ($reference && is_file(__DIR__ . '/' . $reference)) return linkto($reference);
    $edition = 'assets/guides/' . $post['slug'] . '-cover.webp';
    if (preg_match('/^[a-z0-9-]+$/D', $post['slug']) && is_file(__DIR__ . '/' . $edition)) return linkto($edition);
    $art = $post['art'] ?: ($post['category'] === 'Outlook' ? 'outlook' : 'stationery');
    return linkto('assets/editorial/' . ($art === 'outlook' ? 'outlook' : 'stationery') . '.webp');
}

function reading_minutes(string $html): int
{
    return max(1, (int) ceil(preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($html)) / 220));
}

function related_posts(array $post): array
{
    $query = db()->prepare("SELECT * FROM posts WHERE status='published' AND id<>? ORDER BY (category=?) DESC,published_at DESC,id DESC LIMIT 3");
    $query->execute([$post['id'], $post['category']]);
    return $query->fetchAll();
}

function published_date(string $stored): DateTimeImmutable
{
    return (new DateTimeImmutable($stored, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
}

function placements(): array
{
    return ['header' => 'Banner đầu trang', 'preview' => 'Dưới khung xem trước', 'article' => 'Trong bài viết', 'footer' => 'Banner cuối trang', 'left' => 'Lề trái trình soạn thảo', 'right' => 'Lề phải trình soạn thảo'];
}

function display_ad_parts(string $code): array
{
    [, $body] = document_fragment($code);
    // libxml may place the loader in either the head or the body.
    $scriptNodes = [];
    $unit = [];
    $invalid = 'Dán mã đơn vị quảng cáo hiển thị AdSense gồm thẻ ins và lệnh adsbygoogle.push.';
    $head = $body->ownerDocument->getElementsByTagName('head')->item(0);
    $nodes = $head ? array_merge(iterator_to_array($head->childNodes), iterator_to_array($body->childNodes)) : iterator_to_array($body->childNodes);
    $client = '';
    $push = false;
    foreach ($nodes as $node) {
        if ($node instanceof DOMComment || ($node instanceof DOMText && trim($node->textContent) === '')) continue;
        if (!$node instanceof DOMElement) throw new InvalidArgumentException($invalid);
        if ($node->tagName === 'meta' && $node->parentNode === $head) continue;
        if ($node->tagName === 'script') {
            $src = $node->getAttribute('src');
            if ($src && preg_match('~^https://pagead2\.googlesyndication\.com/pagead/js/adsbygoogle\.js(?:\?client=(ca-pub-\d{16}))?$~D', $src, $matches)
                && trim($node->textContent) === '' && !$scriptNodes) {
                $scriptNodes = [$src, $matches[1] ?? ''];
                continue;
            }
            if (!$src && !$push && preg_match('~^\s*\(adsbygoogle\s*=\s*window\.adsbygoogle\s*\|\|\s*\[\s*\]\s*\)\.push\(\s*\{\s*\}\s*\)\s*;?\s*$~D', $node->textContent)) {
                $push = true;
                continue;
            }
        } elseif ($node->tagName === 'ins' && !$unit && $node->getAttribute('class') === 'adsbygoogle' && !$node->getElementsByTagName('*')->length && trim($node->textContent) === '') {
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $value = trim($attribute->value);
                $key = $attribute->name;
                if (in_array($key, ['class','data-ad-client','data-ad-slot','data-ad-format','data-ad-layout','data-ad-layout-key','data-full-width-responsive'], true)
                    && preg_match('~^[a-z0-9 +_-]{1,65}$~iD', $value)) {
                    $unit[$key] = $value;
                } elseif ($key === 'style'
                    && preg_match('~^(?:(?:display\s*:\s*(?:block|inline-block)|(?:width|height|min-width|max-width|min-height|max-height)\s*:\s*\d{1,4}(?:px|%)|text-align\s*:\s*center)\s*;?\s*)+$~iD', $value)) {
                    $unit[$key] = $value;
                } else {
                    throw new InvalidArgumentException($invalid);
                }
            }
            $client = $unit['data-ad-client'] ?? '';
            continue;
        }
        throw new InvalidArgumentException($invalid);
    }
    if (!$push || !preg_match('~^ca-pub-\d{16}$~D', $client)
        || !preg_match('~^\d{1,20}$~D', $unit['data-ad-slot'] ?? '')
        || (!empty($scriptNodes[1]) && $client !== $scriptNodes[1])) {
        throw new InvalidArgumentException($invalid);
    }
    $unit['style'] ??= 'display:block';
    return [$client, $unit];
}

function ad_gallery(array $item): array
{
    $gallery = json_decode($item['gallery'] ?? '[]', true);
    return array_values(array_filter(is_array($gallery) ? $gallery : [], static fn($name): bool => is_string($name)
        && preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/D', $name)));
}

function activity_start(int $days): string
{
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Ho_Chi_Minh')))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
}

function popular_templates(int $days, int $limit): array
{
    $query = db()->prepare("SELECT t.id,t.name,SUM(a.total) AS total FROM activity_counts a
        JOIN templates t ON CAST(t.id AS TEXT)=a.entity WHERE a.event='select' AND a.day>=? AND t.visible=1
        GROUP BY t.id ORDER BY total DESC,t.id LIMIT ?");
    $query->bindValue(1, activity_start($days));
    $query->bindValue(2, $limit, PDO::PARAM_INT);
    $query->execute();
    return $query->fetchAll();
}

function ad(string $placement): bool
{
    $query = db()->prepare('SELECT * FROM ads WHERE placement=? AND enabled=1');
    $query->execute([$placement]);
    $item = $query->fetch();
    if (!$item) return false;
    if ($item['mode'] === 'adsense' && $item['code'] !== '') {
        static $loaded = false;
        try {
            [$client, $attributes] = display_ad_parts($item['code']);
        } catch (InvalidArgumentException $error) {
            error_log($error->getMessage());
            return false;
        }
        echo '<aside class="ad-slot adsense-slot placement-' . h($placement) . '" aria-label="Quảng cáo"><small>Quảng cáo</small>';
        if (!$loaded) {
            echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . h($client) . '" crossorigin="anonymous"></script>';
            $loaded = true;
        }
        echo '<ins';
        foreach ($attributes as $key => $value) echo ' ' . $key . '="' . h($value) . '"';
        echo '></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script></aside>';
        return true;
    }
    if ($item['mode'] !== 'banner' || $item['image'] === '' || !web_url($item['target'])) return false;
    $tone = in_array($item['tone'], ['forest','sun','ink'], true) ? $item['tone'] : 'forest';
    $images = array_merge([$item['image']], ad_gallery($item));
    $effect = in_array($item['transition'], ['fade','slide','glow','manual'], true) ? $item['transition'] : 'fade';
    ?>
    <aside class="ad-slot partner-banner partner-<?= h($tone) ?> partner-<?= h($placement) ?>" aria-label="Quảng cáo" <?= count($images) > 1 ? 'data-carousel="' . h($effect) . '"' : '' ?>>
        <a target="_blank" rel="sponsored noopener noreferrer" href="<?= h(linkto('affiliate.php?slot=' . $placement)) ?>">
            <span class="partner-media">
                <?php foreach ($images as $index => $image): ?>
                <img loading="lazy" <?= $index ? 'hidden' : '' ?> src="<?= h(linkto('uploads/' . $image)) ?>" alt="<?= h($item['alt'] ?: 'Ảnh quảng cáo') ?>">
                <?php endforeach; ?>
            </span>
            <span class="partner-copy">
                <small>Quảng cáo</small>
                <?php if ($item['headline']): ?><strong><?= h($item['headline']) ?></strong><?php endif; ?>
                <?php if ($item['description']): ?><span><?= h($item['description']) ?></span><?php endif; ?>
                <?php if ($item['button_text']): ?><span class="partner-action"><?= h($item['button_text']) ?></span><?php endif; ?>
            </span>
        </a>
        <?php if (count($images) > 1): ?>
        <div class="partner-controls" hidden>
            <button type="button" data-slide="prev" aria-label="Ảnh trước">‹</button>
            <span data-slide-count>1 / <?= count($images) ?></span>
            <button type="button" data-slide="next" aria-label="Ảnh tiếp theo">›</button>
            <?php if ($effect !== 'manual'): ?><button type="button" data-slide="pause" aria-label="Tạm dừng chuyển ảnh">Ⅱ</button><?php endif; ?>
        </div>
        <?php endif; ?>
    </aside>
    <?php
    return true;
}
