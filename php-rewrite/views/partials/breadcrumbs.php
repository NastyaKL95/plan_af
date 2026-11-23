<?php
/** @var list<array{label: string, url?: string|null}>|null $breadcrumbs */
if (empty($breadcrumbs) || !is_array($breadcrumbs)) {
    return;
}
$breadcrumbSegments = array_values(array_filter($breadcrumbs, static fn ($c): bool => is_array($c) && trim((string) ($c['label'] ?? '')) !== ''));
if ($breadcrumbSegments === []) {
    return;
}
?>
<nav aria-label="Навигационная цепочка" class="iro-breadcrumb-wrap mb-3">
    <ol class="iro-breadcrumb list-unstyled d-flex flex-wrap align-items-center gap-1 gap-sm-2 mb-0 small">
        <?php foreach ($breadcrumbSegments as $i => $c): ?>
            <?php
            $label = trim((string) ($c['label'] ?? ''));
            $url = array_key_exists('url', $c) ? $c['url'] : null;
            $isLast = $i === count($breadcrumbSegments) - 1;
            $isLink = !$isLast && $url !== null && $url !== '';
            ?>
            <?php if ($i > 0): ?>
                <li class="iro-breadcrumb-sep text-muted" aria-hidden="true"><i class="bi bi-chevron-right"></i></li>
            <?php endif; ?>
            <li class="iro-breadcrumb-item d-flex align-items-center min-w-0<?= $isLast ? ' iro-breadcrumb-item--current' : '' ?>"<?= $isLast ? ' aria-current="page"' : '' ?>>
                <?php
                $homeFirst = $i === 0 && $isLink && ((string) $url === '/' || $label === 'Главная');
                ?>
                <?php if ($homeFirst): ?>
                    <a href="<?= h((string) $url) ?>" class="iro-breadcrumb-link iro-breadcrumb-link--home text-decoration-none d-inline-flex align-items-center gap-1">
                        <i class="bi bi-house-door-fill" aria-hidden="true"></i><span class="visually-hidden"><?= h($label) ?></span>
                    </a>
                <?php elseif ($isLink): ?>
                    <a href="<?= h((string) $url) ?>" class="iro-breadcrumb-link text-decoration-none text-truncate"><?= h($label) ?></a>
                <?php else: ?>
                    <span class="iro-breadcrumb-current text-truncate d-inline-block"><?= h($label) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
