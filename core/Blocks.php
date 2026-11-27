<?php
declare(strict_types=1);

/**
 * Block definitions. The same schema drives server-side sanitizing and the visual editor.
 * Field types: text, textarea, rich, url, image, select, code, list.
 */
final class Blocks
{
    public const MAX_BLOCKS = 200;

    /** Where a section sits and how wide it is ('' = follow the page). */
    public const PLACEMENT_ALIGN = ['' => 'Page default', 'left' => 'Left', 'center' => 'Center', 'right' => 'Right'];

    /** Popup choices. The browser remembers "how often" in its own storage: no cookies, nothing sent to the server. */
    public const POPUP_KIND = ['modal' => 'Centered box', 'bar' => 'Bar across the screen', 'corner' => 'Corner card'];
    public const POPUP_PLACE = ['auto' => 'Automatic (bar at the top, card bottom right)', 'top' => 'Top (bar)', 'bottom' => 'Bottom (bar)', 'left' => 'Bottom left (card)', 'right' => 'Bottom right (card)'];
    public const POPUP_TRIGGER = ['load' => 'Right away', 'delay5' => 'After 5 seconds', 'delay10' => 'After 10 seconds', 'delay30' => 'After 30 seconds', 'scroll' => 'After scrolling halfway down', 'exit' => 'When the visitor moves to leave (computers)'];
    public const POPUP_OFTEN = ['always' => 'Every time the page opens', 'visit' => 'Once per visit', 'day' => 'Once a day', 'week' => 'Once a week', 'month' => 'Once a month', 'once' => 'Only once'];
    public const PLACEMENT_WIDTH = ['' => 'Page default', 'narrow' => 'Narrow', 'medium' => 'Medium', 'wide' => 'Wide', 'full' => 'Full width'];

    /** Simple line icons drawn for Teeny (24×24, stroke-based), with no external icon fonts. */
    public const SOCIAL = [
        'instagram' => ['label' => 'Instagram', 'svg' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r=".6" fill="currentColor"/>'],
        'x'         => ['label' => 'X (Twitter)', 'svg' => '<path d="M4.5 4h4.2l10.8 16h-4.2zM19.2 4l-6 6.9M4.8 20l6-6.9"/>'],
        'facebook'  => ['label' => 'Facebook', 'svg' => '<path d="M14.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.6 1.6-1.6h1.5V4.3a19 19 0 0 0-2.3-.1c-2.3 0-3.8 1.4-3.8 3.9v2.4H9v3h2.5V21"/>'],
        'linkedin'  => ['label' => 'LinkedIn', 'svg' => '<rect x="3.5" y="3.5" width="17" height="17" rx="2.5"/><path d="M8 10.5V16M8 7.6v.1M11.5 16v-5.5M11.5 13c0-1.6 1-2.6 2.3-2.6s2.2.9 2.2 2.6v3"/>'],
        'youtube'   => ['label' => 'YouTube', 'svg' => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10.3 9.3 4.6 2.7-4.6 2.7z" fill="currentColor"/>'],
        'tiktok'    => ['label' => 'TikTok', 'svg' => '<path d="M13.5 3.5v11.3a3.2 3.2 0 1 1-3.2-3.2M13.5 3.5c.4 2.6 2.1 4.2 4.8 4.4"/>'],
        'threads'   => ['label' => 'Threads', 'svg' => '<path d="M16.5 11.2c-.6-2.2-2.3-3.2-4.4-3.2-2.7 0-4.4 2-4.4 4.6 0 2.9 1.8 4.9 4.6 4.9 2.6 0 4.4-1.5 4.4-3.6 0-2.2-2.2-3.1-4.6-2.8-1.6.2-2.6 1-2.6 2.1s1 1.8 2.3 1.8c2.4 0 3.4-2.2 3.2-5.2"/><path d="M19.8 7.5C18.3 4.9 15.6 3.5 12.2 3.5 7 3.5 3.8 7 3.8 12s3.2 8.5 8.4 8.5c3.5 0 6.2-1.5 7.6-4.2"/>'],
        'github'    => ['label' => 'GitHub', 'svg' => '<circle cx="6.5" cy="5.5" r="2"/><circle cx="6.5" cy="18.5" r="2"/><circle cx="17.5" cy="8.5" r="2"/><path d="M6.5 7.5v9M17.5 10.5c0 4-5 3-9.5 6.5"/>'],
        'pinterest' => ['label' => 'Pinterest', 'svg' => '<circle cx="12" cy="12" r="8.5"/><path d="M10.8 8.5h2.4a2.6 2.6 0 0 1 0 5.2h-2.4M10.8 8.5 9 20"/>'],
        'dribbble'  => ['label' => 'Dribbble', 'svg' => '<circle cx="12" cy="12" r="8.5"/><path d="M5.5 6.8c4.8 2.3 9 7.3 10.8 12.6M3.6 11.4c5.5.4 10.3-1.3 13.6-5.2M8.4 19.8c2.3-4 6.3-6 11.9-5"/>'],
        'behance'   => ['label' => 'Behance', 'svg' => '<path d="M3 6.5h5a2.5 2.5 0 0 1 0 5H3zM3 11.5h5.7a2.7 2.7 0 0 1 0 5.4H3zM3 6.5v10.4M14.5 13.5h6.5a3.3 3.3 0 0 0-6.5 0 3.3 3.3 0 0 0 6.2 1.6M15 7.5h5"/>'],
        'whatsapp'  => ['label' => 'WhatsApp', 'svg' => '<path d="M4 20.5 5.3 16A8.5 8.5 0 1 1 8.2 19z"/><path d="M9.3 8.3c-.4 1.9 1.6 5.3 4.8 6.4.7.2 1.6-.2 1.9-1l-1.6-1-1 .7c-1-.4-2-1.4-2.4-2.4l.7-1-1-1.6c-.7.1-1.2.4-1.4 1z"/>'],
        'telegram'  => ['label' => 'Telegram', 'svg' => '<path d="m3.5 11.2 16.5-6.7-2.8 15.3-5.3-4.2-2.8 2.6.4-4.4 7.5-6.6-9.4 5.6z"/>'],
        'email'     => ['label' => 'Email', 'svg' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.5 7 8.5 6.5L20.5 7"/>'],
        'phone'     => ['label' => 'Phone', 'svg' => '<path d="M6.5 3.5h3l1.5 4.5-2 1.3a10 10 0 0 0 5.7 5.7l1.3-2 4.5 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2z"/>'],
        'website'   => ['label' => 'Website', 'svg' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.4 2.4 3.5 5.3 3.5 8.5s-1.1 6.1-3.5 8.5c-2.4-2.4-3.5-5.3-3.5-8.5s1.1-6.1 3.5-8.5z"/>'],
    ];

    public static function socialSvg(string $network): string
    {
        $n = self::SOCIAL[$network] ?? self::SOCIAL['website'];
        return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $n['svg'] . '</svg>';
    }

    /** Built-in sections plus sections added by enabled plugins. */
    public static function schema(): array
    {
        static $cache = null;
        return $cache ??= self::builtin() + Plugins::schemas();
    }

    public static function builtin(): array
    {
        return [
            'hero' => [
                'label' => 'Hero', 'group' => 'Layout', 'desc' => 'Big title with intro and button',
                'fields' => [
                    'eyebrow' => ['type' => 'text', 'label' => 'Small label', 'max' => 80],
                    'title'   => ['type' => 'text', 'label' => 'Title', 'max' => 160],
                    'text'    => ['type' => 'textarea', 'label' => 'Intro', 'max' => 700],
                    'button'  => ['type' => 'text', 'label' => 'Button text', 'max' => 60],
                    'url'     => ['type' => 'url', 'label' => 'Button link'],
                    'image'   => ['type' => 'image', 'label' => 'Image'],
                    'style'   => ['type' => 'select', 'label' => 'Image style', 'options' => ['none' => 'No image', 'side' => 'Beside the text', 'cover' => 'Full background']],
                ],
                'defaults' => ['eyebrow' => '', 'title' => 'A beautiful headline', 'text' => 'Say what you do in one or two calm sentences.', 'button' => 'Get in touch', 'url' => '#', 'image' => '', 'style' => 'none'],
            ],
            'profile' => [
                'label' => 'Profile', 'group' => 'Layout', 'desc' => 'Photo, name and short bio',
                'fields' => [
                    'avatar' => ['type' => 'image', 'label' => 'Photo'],
                    'name'   => ['type' => 'text', 'label' => 'Name', 'max' => 100],
                    'bio'    => ['type' => 'textarea', 'label' => 'Bio', 'max' => 400],
                ],
                'defaults' => ['avatar' => '', 'name' => 'Your Name', 'bio' => 'Designer · Writer · Somewhere lovely'],
            ],
            'heading' => [
                'label' => 'Heading', 'group' => 'Text', 'desc' => 'Section title',
                'fields' => [
                    'text'  => ['type' => 'text', 'label' => 'Heading', 'max' => 200],
                    'level' => ['type' => 'select', 'label' => 'Size', 'options' => ['h2' => 'Large', 'h3' => 'Medium', 'h1' => 'Extra large']],
                ],
                'defaults' => ['text' => 'Section heading', 'level' => 'h2'],
            ],
            'text' => [
                'label' => 'Text', 'group' => 'Text', 'desc' => 'Paragraphs, lists and links',
                'fields' => [
                    'html' => ['type' => 'rich', 'label' => 'Text'],
                ],
                'defaults' => ['html' => '<p>Start writing here. Select text to make it <strong>bold</strong>, <em>italic</em> or a link.</p>'],
            ],
            'quote' => [
                'label' => 'Quote', 'group' => 'Text', 'desc' => 'Testimonial or quotation',
                'fields' => [
                    'text' => ['type' => 'textarea', 'label' => 'Quote', 'max' => 800],
                    'cite' => ['type' => 'text', 'label' => 'Author', 'max' => 120],
                ],
                'defaults' => ['text' => 'Simplicity is the ultimate sophistication.', 'cite' => 'Someone wise'],
            ],
            'code' => [
                'label' => 'Code', 'group' => 'Text', 'desc' => 'Code snippet for docs',
                'fields' => [
                    'code' => ['type' => 'code', 'label' => 'Code'],
                    'lang' => ['type' => 'text', 'label' => 'Language label', 'max' => 30],
                ],
                'defaults' => ['code' => 'echo "Hello, world";', 'lang' => 'Example'],
            ],
            'image' => [
                'label' => 'Image', 'group' => 'Images & video', 'desc' => 'Single picture with caption',
                'fields' => [
                    'src'     => ['type' => 'image', 'label' => 'Image'],
                    'alt'     => ['type' => 'text', 'label' => 'Description (for screen readers)', 'max' => 200],
                    'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 300],
                    'size'    => ['type' => 'select', 'label' => 'Width', 'options' => ['normal' => 'Normal', 'wide' => 'Wide', 'full' => 'Full width']],
                ],
                'defaults' => ['src' => '', 'alt' => '', 'caption' => '', 'size' => 'normal'],
            ],
            'gallery' => [
                'label' => 'Gallery', 'group' => 'Images & video', 'desc' => 'Grid of images',
                'fields' => [
                    'images' => ['type' => 'list', 'label' => 'Images', 'max' => 60, 'item' => [
                        'src'     => ['type' => 'image', 'label' => 'Image'],
                        'alt'     => ['type' => 'text', 'label' => 'Description', 'max' => 200],
                        'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 200],
                    ]],
                    'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => ['3' => '3 columns', '2' => '2 columns', '4' => '4 columns']],
                ],
                'defaults' => ['images' => [['src' => '', 'alt' => '', 'caption' => ''], ['src' => '', 'alt' => '', 'caption' => ''], ['src' => '', 'alt' => '', 'caption' => '']], 'columns' => '3'],
            ],
            'embed' => [
                'label' => 'Video', 'group' => 'Images & video', 'desc' => 'YouTube or Vimeo video',
                'fields' => [
                    'url'     => ['type' => 'url', 'label' => 'YouTube or Vimeo link'],
                    'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 200],
                ],
                'defaults' => ['url' => '', 'caption' => ''],
            ],
            'button' => [
                'label' => 'Button', 'group' => 'Links', 'desc' => 'Single call-to-action',
                'fields' => [
                    'text'  => ['type' => 'text', 'label' => 'Text', 'max' => 60],
                    'url'   => ['type' => 'url', 'label' => 'Link'],
                    'style' => ['type' => 'select', 'label' => 'Style', 'options' => ['solid' => 'Solid', 'outline' => 'Outline', 'link' => 'Text link']],
                ],
                'defaults' => ['text' => 'Learn more', 'url' => '#', 'style' => 'solid'],
            ],
            'links' => [
                'label' => 'Link list', 'group' => 'Links', 'desc' => 'Stacked links (link-in-bio)',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Links', 'max' => 40, 'item' => [
                        'label' => ['type' => 'text', 'label' => 'Label', 'max' => 80],
                        'url'   => ['type' => 'url', 'label' => 'Link'],
                    ]],
                ],
                'defaults' => ['items' => [['label' => 'My website', 'url' => '#'], ['label' => 'Instagram', 'url' => '#'], ['label' => 'Email me', 'url' => 'mailto:you@example.com']]],
            ],
            'columns' => [
                'label' => 'Columns', 'group' => 'Layout', 'desc' => 'Features or services side by side',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Columns', 'max' => 4, 'item' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 100],
                        'text'  => ['type' => 'textarea', 'label' => 'Text', 'max' => 600],
                    ]],
                ],
                'defaults' => ['items' => [['title' => 'First', 'text' => 'A short description.'], ['title' => 'Second', 'text' => 'A short description.'], ['title' => 'Third', 'text' => 'A short description.']]],
            ],
            'faq' => [
                'label' => 'FAQ', 'group' => 'Text', 'desc' => 'Questions that open to show answers',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Questions', 'max' => 40, 'item' => [
                        'q' => ['type' => 'text', 'label' => 'Question', 'max' => 200],
                        'a' => ['type' => 'textarea', 'label' => 'Answer', 'max' => 2000],
                    ]],
                ],
                'defaults' => ['items' => [
                    ['q' => 'How long does it take?', 'a' => 'Most projects are ready within two weeks.'],
                    ['q' => 'Can I change things later?', 'a' => 'Of course. Everything can be edited at any time.'],
                    ['q' => 'How do I get in touch?', 'a' => 'Use the contact form or send us an email.'],
                ]],
            ],
            'pricing' => [
                'label' => 'Pricing', 'group' => 'Layout', 'desc' => 'Plans with prices and features',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Plans', 'max' => 4, 'item' => [
                        'name'     => ['type' => 'text', 'label' => 'Plan name', 'max' => 60],
                        'price'    => ['type' => 'text', 'label' => 'Price', 'max' => 30],
                        'period'   => ['type' => 'text', 'label' => 'Period (e.g. / month)', 'max' => 30],
                        'features' => ['type' => 'textarea', 'label' => 'Features (one per line)', 'max' => 1200],
                        'button'   => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                        'url'      => ['type' => 'url', 'label' => 'Button link'],
                        'featured' => ['type' => 'select', 'label' => 'Highlight', 'options' => ['no' => 'Normal', 'yes' => 'Highlighted']],
                    ]],
                ],
                'defaults' => ['items' => [
                    ['name' => 'Starter', 'price' => '$9', 'period' => '/ month', 'features' => "One website\nAll themes\nEmail support", 'button' => 'Choose Starter', 'url' => '#', 'featured' => 'no'],
                    ['name' => 'Studio', 'price' => '$29', 'period' => '/ month', 'features' => "Five websites\nAll themes\nPriority support\nCustom domain", 'button' => 'Choose Studio', 'url' => '#', 'featured' => 'yes'],
                    ['name' => 'Agency', 'price' => '$79', 'period' => '/ month', 'features' => "Unlimited websites\nWhite label\nPersonal onboarding", 'button' => 'Contact us', 'url' => '#', 'featured' => 'no'],
                ]],
            ],
            'social' => [
                'label' => 'Social icons', 'group' => 'Links', 'desc' => 'Row of social media icons',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Profiles', 'max' => 16, 'item' => [
                        'network' => ['type' => 'select', 'label' => 'Network', 'options' => array_map(static fn($n) => $n['label'], self::SOCIAL)],
                        'url'     => ['type' => 'url', 'label' => 'Profile link'],
                    ]],
                    'size' => ['type' => 'select', 'label' => 'Size', 'options' => ['m' => 'Medium', 's' => 'Small', 'l' => 'Large']],
                ],
                'defaults' => ['items' => [
                    ['network' => 'instagram', 'url' => 'https://instagram.com/'],
                    ['network' => 'x', 'url' => 'https://x.com/'],
                    ['network' => 'linkedin', 'url' => 'https://linkedin.com/'],
                    ['network' => 'email', 'url' => 'mailto:you@example.com'],
                ], 'size' => 'm'],
            ],
            'contact' => [
                'label' => 'Contact form', 'group' => 'Links', 'desc' => 'Visitors send you a message',
                'fields' => [
                    'phone'   => ['type' => 'select', 'label' => 'Phone field', 'options' => ['no' => 'Hide phone field', 'yes' => 'Ask for phone number']],
                    'button'  => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                    'success' => ['type' => 'textarea', 'label' => 'Thank-you message', 'max' => 300],
                ],
                'defaults' => ['phone' => 'no', 'button' => 'Send message', 'success' => 'Thank you! Your message has been sent. We will reply soon.'],
            ],
            'split' => [
                'label' => 'Image + text', 'group' => 'Layout', 'desc' => 'Picture beside text and a button',
                'fields' => [
                    'image'  => ['type' => 'image', 'label' => 'Image'],
                    'alt'    => ['type' => 'text', 'label' => 'Image description', 'max' => 200],
                    'title'  => ['type' => 'text', 'label' => 'Title', 'max' => 160],
                    'html'   => ['type' => 'rich', 'label' => 'Text'],
                    'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 60],
                    'url'    => ['type' => 'url', 'label' => 'Button link'],
                    'side'   => ['type' => 'select', 'label' => 'Image side', 'options' => ['left' => 'Image on the left', 'right' => 'Image on the right']],
                ],
                'defaults' => ['image' => '', 'alt' => '', 'title' => 'Crafted with care', 'html' => '<p>Tell the story behind your work. A few honest sentences go a long way.</p>', 'button' => 'Learn more', 'url' => '#', 'side' => 'left'],
            ],
            'steps' => [
                'label' => 'Steps', 'group' => 'Layout', 'desc' => 'How it works, 1 → 2 → 3',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Steps', 'max' => 8, 'item' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 100],
                        'text'  => ['type' => 'textarea', 'label' => 'Text', 'max' => 500],
                    ]],
                ],
                'defaults' => ['items' => [
                    ['title' => 'Say hello', 'text' => 'Tell us what you have in mind.'],
                    ['title' => 'We plan together', 'text' => 'A clear proposal with time and price.'],
                    ['title' => 'Enjoy the result', 'text' => 'Delivered on time, beautifully.'],
                ]],
            ],
            'tabs' => [
                'label' => 'Tabs', 'group' => 'Text', 'desc' => 'Switch between panels of text',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Tabs', 'max' => 8, 'item' => [
                        'label' => ['type' => 'text', 'label' => 'Tab name', 'max' => 40],
                        'text'  => ['type' => 'textarea', 'label' => 'Content', 'max' => 5000],
                    ]],
                    'mono' => ['type' => 'select', 'label' => 'Text style', 'options' => ['no' => 'Normal text', 'yes' => 'Code (monospace)']],
                ],
                'defaults' => ['items' => [
                    ['label' => 'Overview', 'text' => 'A short overview of the topic.'],
                    ['label' => 'Details', 'text' => 'More details for those who want them.'],
                    ['label' => 'FAQ', 'text' => 'Answers to common questions.'],
                ], 'mono' => 'no'],
            ],
            'callout' => [
                'label' => 'Callout', 'group' => 'Text', 'desc' => 'Tip, note, warning or success box',
                'fields' => [
                    'kind'  => ['type' => 'select', 'label' => 'Type', 'options' => ['tip' => 'Tip', 'note' => 'Note', 'warning' => 'Warning', 'success' => 'Success']],
                    'title' => ['type' => 'text', 'label' => 'Title', 'max' => 100],
                    'text'  => ['type' => 'textarea', 'label' => 'Text', 'max' => 1000],
                ],
                'defaults' => ['kind' => 'tip', 'title' => 'Good to know', 'text' => 'Keep important hints short and friendly.'],
            ],
            'table' => [
                'label' => 'Table', 'group' => 'Text', 'desc' => 'Comparison or spec table',
                'fields' => [
                    'title'    => ['type' => 'text', 'label' => 'Title (optional)', 'max' => 120],
                    'header'   => ['type' => 'text', 'label' => 'Column names (separate with | )', 'max' => 500],
                    'rows'     => ['type' => 'textarea', 'label' => 'Rows: one per line, cells separated with | (write yes or no for ✓ / ✕)', 'max' => 8000],
                    'firstcol' => ['type' => 'select', 'label' => 'First column', 'options' => ['yes' => 'Highlight first column', 'no' => 'Normal']],
                ],
                'defaults' => ['title' => '', 'header' => 'Feature | Basic | Pro', 'rows' => "Pages | 5 | Unlimited\nCustom domain | no | yes\nPriority support | no | yes", 'firstcol' => 'yes'],
            ],
            'events' => [
                'label' => 'Events', 'group' => 'Text', 'desc' => 'Dates, times and places',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Events', 'max' => 40, 'item' => [
                        'date'  => ['type' => 'text', 'label' => 'Date (e.g. 12 Dec)', 'max' => 30],
                        'time'  => ['type' => 'text', 'label' => 'Time', 'max' => 30],
                        'title' => ['type' => 'text', 'label' => 'Event', 'max' => 120],
                        'place' => ['type' => 'text', 'label' => 'Place', 'max' => 120],
                        'url'   => ['type' => 'url', 'label' => 'Link (optional)'],
                    ]],
                    'linkText' => ['type' => 'text', 'label' => 'Link button text', 'max' => 30],
                ],
                'defaults' => ['items' => [
                    ['date' => '12 Dec', 'time' => '19:00', 'title' => 'Opening night', 'place' => 'The Studio, Main Street', 'url' => ''],
                    ['date' => '18 Jan', 'time' => '10:00', 'title' => 'Weekend workshop', 'place' => 'Online', 'url' => ''],
                ], 'linkText' => 'Details'],
            ],
            'stats' => [
                'label' => 'Numbers', 'group' => 'Business', 'desc' => 'Big figures like “120+ clients”',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Numbers', 'max' => 6, 'item' => [
                        'value' => ['type' => 'text', 'label' => 'Number', 'max' => 20],
                        'label' => ['type' => 'text', 'label' => 'Label', 'max' => 60],
                    ]],
                ],
                'defaults' => ['items' => [['value' => '120+', 'label' => 'Happy clients'], ['value' => '8', 'label' => 'Years of experience'], ['value' => '4.9', 'label' => 'Average rating']]],
            ],
            'cta' => [
                'label' => 'Call to action', 'group' => 'Business', 'desc' => 'Coloured band with a button',
                'fields' => [
                    'title'  => ['type' => 'text', 'label' => 'Title', 'max' => 160],
                    'text'   => ['type' => 'textarea', 'label' => 'Text', 'max' => 400],
                    'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 60],
                    'url'    => ['type' => 'url', 'label' => 'Button link'],
                    'style'  => ['type' => 'select', 'label' => 'Colour', 'options' => ['accent' => 'Accent colour', 'dark' => 'Dark', 'soft' => 'Soft']],
                ],
                'defaults' => ['title' => 'Ready to begin?', 'text' => 'Let’s make something beautiful together.', 'button' => 'Get in touch', 'url' => '#', 'style' => 'accent'],
            ],
            'logos' => [
                'label' => 'Logo strip', 'group' => 'Business', 'desc' => '“Trusted by” row of logos',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title', 'max' => 80],
                    'items' => ['type' => 'list', 'label' => 'Logos', 'max' => 16, 'item' => [
                        'image' => ['type' => 'image', 'label' => 'Logo'],
                        'alt'   => ['type' => 'text', 'label' => 'Company name', 'max' => 80],
                        'url'   => ['type' => 'url', 'label' => 'Link (optional)'],
                    ]],
                    'mono' => ['type' => 'select', 'label' => 'Colour', 'options' => ['yes' => 'Soft grey (elegant)', 'no' => 'Original colours']],
                ],
                'defaults' => ['title' => 'Trusted by', 'items' => [['image' => '', 'alt' => '', 'url' => ''], ['image' => '', 'alt' => '', 'url' => ''], ['image' => '', 'alt' => '', 'url' => ''], ['image' => '', 'alt' => '', 'url' => '']], 'mono' => 'yes'],
            ],
            'team' => [
                'label' => 'Team', 'group' => 'Business', 'desc' => 'Photos, names and roles',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'People', 'max' => 24, 'item' => [
                        'photo' => ['type' => 'image', 'label' => 'Photo'],
                        'name'  => ['type' => 'text', 'label' => 'Name', 'max' => 80],
                        'role'  => ['type' => 'text', 'label' => 'Role', 'max' => 80],
                        'url'   => ['type' => 'url', 'label' => 'Profile link (optional)'],
                    ]],
                    'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => ['3' => '3 columns', '2' => '2 columns', '4' => '4 columns']],
                ],
                'defaults' => ['items' => [['photo' => '', 'name' => 'Alex Morgan', 'role' => 'Founder', 'url' => ''], ['photo' => '', 'name' => 'Sam Lee', 'role' => 'Designer', 'url' => ''], ['photo' => '', 'name' => 'Noor Aziz', 'role' => 'Developer', 'url' => '']], 'columns' => '3'],
            ],
            'products' => [
                'label' => 'Products', 'group' => 'Business', 'desc' => 'Items with price and buy button',
                'fields' => [
                    'items' => ['type' => 'list', 'label' => 'Products', 'max' => 24, 'item' => [
                        'image'  => ['type' => 'image', 'label' => 'Image'],
                        'name'   => ['type' => 'text', 'label' => 'Name', 'max' => 100],
                        'price'  => ['type' => 'text', 'label' => 'Price', 'max' => 30],
                        'desc'   => ['type' => 'textarea', 'label' => 'Short description', 'max' => 300],
                        'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                        'url'    => ['type' => 'url', 'label' => 'Button link (shop, WhatsApp, email…)'],
                    ]],
                    'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => ['3' => '3 columns', '2' => '2 columns', '4' => '4 columns']],
                    'whatsapp' => ['type' => 'text', 'label' => 'WhatsApp number for orders (optional, e.g. +60123456789)', 'max' => 20],
                ],
                'defaults' => ['whatsapp' => '', 'items' => [
                    ['image' => '', 'name' => 'Linen tote', 'price' => '$35', 'desc' => 'Hand-stitched, natural linen.', 'button' => 'Order', 'url' => '#'],
                    ['image' => '', 'name' => 'Ceramic cup', 'price' => '$22', 'desc' => 'Glazed by hand, one of a kind.', 'button' => 'Order', 'url' => '#'],
                    ['image' => '', 'name' => 'Art print', 'price' => '$48', 'desc' => 'A3 giclée on cotton paper.', 'button' => 'Order', 'url' => '#'],
                ], 'columns' => '3'],
            ],
            'compare' => [
                'label' => 'Before / after', 'group' => 'Images & video', 'desc' => 'Drag to compare two images',
                'fields' => [
                    'before'      => ['type' => 'image', 'label' => 'Before image'],
                    'after'       => ['type' => 'image', 'label' => 'After image'],
                    'beforeLabel' => ['type' => 'text', 'label' => 'Before label', 'max' => 30],
                    'afterLabel'  => ['type' => 'text', 'label' => 'After label', 'max' => 30],
                    'caption'     => ['type' => 'text', 'label' => 'Caption', 'max' => 200],
                ],
                'defaults' => ['before' => '', 'after' => '', 'beforeLabel' => 'Before', 'afterLabel' => 'After', 'caption' => ''],
            ],
            'announce' => [
                'label' => 'Announcement', 'group' => 'Links', 'desc' => 'Slim message strip with a link',
                'fields' => [
                    'text'     => ['type' => 'text', 'label' => 'Message', 'max' => 160],
                    'linkText' => ['type' => 'text', 'label' => 'Link text', 'max' => 40],
                    'url'      => ['type' => 'url', 'label' => 'Link'],
                    'style'    => ['type' => 'select', 'label' => 'Colour', 'options' => ['accent' => 'Accent colour', 'dark' => 'Dark', 'soft' => 'Soft']],
                ],
                'defaults' => ['text' => 'New collection is out now', 'linkText' => 'Take a look', 'url' => '#', 'style' => 'accent'],
            ],
            'popup' => [
                'label' => 'Popup', 'group' => 'Popups', 'desc' => 'Box, bar or corner card that appears over the page',
                'fields' => [
                    'kind'    => ['type' => 'select', 'label' => 'Type', 'options' => self::POPUP_KIND],
                    'place'   => ['type' => 'select', 'label' => 'Position', 'options' => self::POPUP_PLACE],
                    'trigger' => ['type' => 'select', 'label' => 'When it appears', 'options' => self::POPUP_TRIGGER],
                    'often'   => ['type' => 'select', 'label' => 'How often', 'options' => self::POPUP_OFTEN],
                    'title'   => ['type' => 'text', 'label' => 'Title', 'max' => 120],
                    'text'    => ['type' => 'textarea', 'label' => 'Message', 'max' => 600],
                    'button'  => ['type' => 'text', 'label' => 'Button text', 'max' => 60],
                    'url'     => ['type' => 'url', 'label' => 'Button link'],
                    'image'   => ['type' => 'image', 'label' => 'Image (centered box only)'],
                    'alt'     => ['type' => 'text', 'label' => 'Image description', 'max' => 200],
                ],
                'defaults' => ['kind' => 'modal', 'place' => 'auto', 'trigger' => 'delay5', 'often' => 'week', 'title' => 'Hello there', 'text' => 'Tell visitors about something new, like an offer, an event or a newsletter.', 'button' => 'Find out more', 'url' => '#', 'image' => '', 'alt' => ''],
            ],
            'newsletter' => [
                'label' => 'Newsletter', 'group' => 'Links', 'desc' => 'Collect email sign-ups',
                'fields' => [
                    'title'   => ['type' => 'text', 'label' => 'Title', 'max' => 120],
                    'text'    => ['type' => 'textarea', 'label' => 'Text', 'max' => 400],
                    'button'  => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                    'success' => ['type' => 'textarea', 'label' => 'Thank-you message', 'max' => 300],
                ],
                'defaults' => ['title' => 'Stay in the loop', 'text' => 'Occasional news, never spam. Unsubscribe any time.', 'button' => 'Subscribe', 'success' => 'Thank you for subscribing!'],
            ],
            'toc' => [
                'label' => 'Table of contents', 'group' => 'Text', 'desc' => 'Links to the headings on this page',
                'fields' => [
                    'title'  => ['type' => 'text', 'label' => 'Title', 'max' => 80],
                    'levels' => ['type' => 'select', 'label' => 'Headings to list', 'options' => ['h2' => 'Main headings (H2)', 'h3' => 'Main and sub-headings (H2 and H3)']],
                ],
                'defaults' => ['title' => 'On this page', 'levels' => 'h2'],
            ],
            'rsvp' => [
                'label' => 'RSVP', 'group' => 'Links', 'desc' => 'Guests reply whether they can come',
                'fields' => [
                    'title'     => ['type' => 'text', 'label' => 'Title', 'max' => 120],
                    'text'      => ['type' => 'textarea', 'label' => 'Text', 'max' => 400],
                    'maxGuests' => ['type' => 'select', 'label' => 'People per reply', 'options' => ['1' => 'Just the guest', '2' => 'Up to 2', '3' => 'Up to 3', '4' => 'Up to 4', '5' => 'Up to 5', '6' => 'Up to 6', '8' => 'Up to 8', '10' => 'Up to 10']],
                    'askNote'   => ['type' => 'select', 'label' => 'Note field', 'options' => ['yes' => 'Ask for a note', 'no' => 'No note']],
                    'noteLabel' => ['type' => 'text', 'label' => 'Note question', 'max' => 80],
                    'deadline'  => ['type' => 'text', 'label' => 'Reply by (YYYY-MM-DD, optional)', 'max' => 10],
                    'button'    => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                    'success'   => ['type' => 'textarea', 'label' => 'Thank-you message', 'max' => 300],
                    'closed'    => ['type' => 'textarea', 'label' => 'Message after the reply date', 'max' => 300],
                ],
                'defaults' => ['title' => 'Will you join us?', 'text' => 'Please let us know if you can come.', 'maxGuests' => '2', 'askNote' => 'yes', 'noteLabel' => 'Anything we should know? (for example dietary needs)', 'deadline' => '', 'button' => 'Send my reply', 'success' => 'Thank you! Your reply has been saved. To change it, reply again with the same email.', 'closed' => 'Replies are now closed. Please contact us if anything has changed.'],
            ],
            'search' => [
                'label' => 'Search', 'group' => 'Links', 'desc' => 'Search box for your whole site',
                'fields' => [
                    'title'       => ['type' => 'text', 'label' => 'Title (optional)', 'max' => 120],
                    'placeholder' => ['type' => 'text', 'label' => 'Text inside the box', 'max' => 60],
                    'button'      => ['type' => 'text', 'label' => 'Button text', 'max' => 30],
                ],
                'defaults' => ['title' => '', 'placeholder' => 'Search this site', 'button' => 'Search'],
            ],
            'divider' => [
                'label' => 'Divider', 'group' => 'Layout', 'desc' => 'Line, ornament or space',
                'fields' => [
                    'style' => ['type' => 'select', 'label' => 'Style', 'options' => ['line' => 'Thin line', 'ornament' => 'Ornament', 'space-s' => 'Small space', 'space-l' => 'Large space']],
                ],
                'defaults' => ['style' => 'line'],
            ],
        ];
    }

    /** Clean an untrusted list of blocks (from the editor, a template or a backup). */
    public static function sanitize(mixed $blocks): array
    {
        if (!is_array($blocks)) return [];
        $schema = self::schema();
        $out = [];
        $seen = [];
        foreach (array_slice(array_values($blocks), 0, self::MAX_BLOCKS) as $b) {
            if (!is_array($b)) continue;
            $type = (string)($b['type'] ?? '');
            if (!isset($schema[$type])) continue;
            $id = (string)($b['id'] ?? '');
            if (!preg_match('/^[a-z0-9]{6,16}$/', $id) || isset($seen[$id])) $id = random_id(4);
            $seen[$id] = true;
            $clean = ['id' => $id, 'type' => $type];
            foreach ($schema[$type]['fields'] as $name => $def) {
                $clean[$name] = self::field($def, $b[$name] ?? ($schema[$type]['defaults'][$name] ?? null));
            }
            // Placement, shared by every section (camelCase keys can't clash with plugin field names).
            $align = (string)($b['boxAlign'] ?? '');
            $width = (string)($b['boxWidth'] ?? '');
            if (isset(self::PLACEMENT_ALIGN[$align]) && $align !== '') $clean['boxAlign'] = $align;
            if (isset(self::PLACEMENT_WIDTH[$width]) && $width !== '') $clean['boxWidth'] = $width;
            $out[] = $clean;
        }
        return $out;
    }

    /**
     * Clean a section's default values against its own field list. Anything that isn't one of
     * the fields (for example "type" or "id") is dropped, and every value is cleaned like saved content.
     */
    public static function cleanDefaults(array $fields, mixed $defaults): array
    {
        $out = [];
        if (!is_array($defaults)) return $out;
        foreach ($fields as $name => $def) {
            if (array_key_exists($name, $defaults)) $out[$name] = self::field($def, $defaults[$name]);
        }
        return $out;
    }

    private static function field(array $def, mixed $v): mixed
    {
        switch ($def['type']) {
            case 'text':
                return Sanitizer::text($v, $def['max'] ?? 200);
            case 'textarea':
                return Sanitizer::text($v, $def['max'] ?? 2000, true);
            case 'rich':
                return Sanitizer::html(is_string($v) ? $v : '');
            case 'url':
                return Sanitizer::url(is_string($v) ? $v : '');
            case 'image':
                return Media::validRef($v) ? $v : '';
            case 'code':
                return Sanitizer::code($v);
            case 'select':
                $v = is_scalar($v) ? (string)$v : '';
                return array_key_exists($v, $def['options']) ? $v : (string)array_key_first($def['options']);
            case 'list':
                $items = [];
                foreach (array_slice(is_array($v) ? array_values($v) : [], 0, $def['max'] ?? 50) as $item) {
                    if (!is_array($item)) continue;
                    $row = [];
                    foreach ($def['item'] as $k => $sub) {
                        $row[$k] = self::field($sub, $item[$k] ?? null);
                    }
                    $items[] = $row;
                }
                return $items;
        }
        return '';
    }

    /** Turn a YouTube/Vimeo link into a privacy-friendly embed URL, or '' if unsupported. */
    public static function embedSrc(string $url): string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d{5,12})~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
        }
        return '';
    }
}
