<?php
/**
 * Teeny CMS: JSON API used by the editor and media library.
 */
declare(strict_types=1);

define('TEENY_DEPTH', 1);
require dirname(__DIR__) . '/core/bootstrap.php';

send_security_headers('admin');
header('X-Robots-Tag: noindex, nofollow');

if (!teeny_installed()) json_out(['ok' => false, 'error' => 'Not installed.'], 503);
Auth::start();
$user = Auth::user();
if (!$user) json_out(['ok' => false, 'error' => 'Your session has expired. Please log in again.'], 401);

$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

if (is_post() && !Auth::checkCsrf()) {
    json_out(['ok' => false, 'error' => 'Security check failed. Please refresh the page.'], 400);
}

function json_body(): array
{
    $raw = (string)file_get_contents('php://input', false, null, 0, 3 * 1048576);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    switch ($action) {
        case 'media':
            if (!Auth::can('media') && !Auth::can('pages')) json_out(['ok' => false, 'error' => 'Not allowed.'], 403);
            json_out(['ok' => true, 'items' => Media::forClient()]);

        case 'upload':
            if (!is_post() || !Auth::can('media')) json_out(['ok' => false, 'error' => 'Not allowed.'], 403);
            $item = Media::upload($_FILES['file'] ?? [], $user['id']);
            $all = Media::forClient();
            $found = array_values(array_filter($all, static fn($m) => $m['name'] === $item['name']));
            json_out(['ok' => true, 'item' => $found[0] ?? null]);

        case 'media_alt':
            if (!is_post() || !Auth::can('media')) json_out(['ok' => false, 'error' => 'Not allowed.'], 403);
            $b = json_body();
            json_out(['ok' => Media::setAlt((string)($b['name'] ?? ''), (string)($b['alt'] ?? ''))]);

        case 'media_delete':
            if (!is_post() || !Auth::can('media')) json_out(['ok' => false, 'error' => 'Not allowed.'], 403);
            $b = json_body();
            json_out(['ok' => Media::delete((string)($b['name'] ?? ''))]);

        case 'save_page':
            if (!is_post() || !Auth::can('pages')) json_out(['ok' => false, 'error' => 'Not allowed.'], 403);
            $b = json_body();
            $in = is_array($b['page'] ?? null) ? $b['page'] : null;
            if (!$in || !isset($in['id']) || !Pages::get((string)$in['id'])) {
                json_out(['ok' => false, 'error' => 'Page not found.'], 404);
            }
            // Editors cannot unpublish the home page by accident.
            if (($in['id'] === (site()['home'] ?? '')) && ($in['status'] ?? '') !== 'published') {
                $in['status'] = 'published';
            }
            // The footer is shared by every page. Only people who may change the site's look (owners) can edit it;
            // anyone who can edit pages can hide it on a page (that's saved with the page).
            if (is_array($b['footer'] ?? null)) {
                if (!Auth::can('appearance')) json_out(['ok' => false, 'error' => 'Only owners can change the footer.'], 403);
                $site = site();
                $site['footer'] = Sanitizer::text($b['footer']['text'] ?? '', 300);
                $fa = (string)($b['footer']['align'] ?? '');
                $site['footerAlign'] = isset(Renderer::FOOTER_ALIGN[$fa]) ? $fa : '';
                Store::write('site', $site);
                site($site);
            }
            $page = Pages::save($in, $user['id']);
            json_out(['ok' => true, 'page' => Pages::forEditor($page), 'url' => page_url($page), 'saved' => date('H:i')]);

        case 'render_block':
            // Server-side preview of a plugin section for the editor canvas (cleaned HTML only).
            if (!is_post() || !Auth::can('pages')) json_out(['ok' => false, 'error' => 'Not allowed.'], 403);
            $b = json_body();
            $clean = Blocks::sanitize([is_array($b['block'] ?? null) ? $b['block'] : []]);
            $block = $clean[0] ?? null;
            if (!$block || !str_starts_with($block['type'], 'plugin-')) json_out(['ok' => false, 'error' => 'This plugin is switched off or missing.'], 404);
            json_out(['ok' => true, 'html' => Renderer::placement(Plugins::render($block, true), $block)]);

        default:
            json_out(['ok' => false, 'error' => 'Unknown action.'], 404);
    }
} catch (RuntimeException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    error_log('Teeny API: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Something went wrong. Please try again.'], 500);
}
