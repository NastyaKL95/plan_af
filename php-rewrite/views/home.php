<div class="iro-full-bleed iro-full-bleed--home">
    <section class="iro-hero iro-hero--home">
        <div class="container position-relative">
            <div class="row align-items-stretch g-4 g-lg-5">
                <div class="col-lg-7 d-flex flex-column">
                    <p class="iro-hero-kicker mb-2">Портал конкурсов</p>
                    <h1 class="mb-2">Найдите свой конкурс</h1>
                    <p class="iro-hero-lead text-muted mb-4">Забайкальский край</p>
                    <form method="get" action="/" class="iro-search">
                        <div class="input-group iro-hero-search-bar shadow-sm rounded-pill overflow-hidden">
                            <?php if (($view_mode ?? 'cards') === 'list'): ?>
                                <input type="hidden" name="view" value="list">
                            <?php endif; ?>
                            <?php if (($phase ?? '') !== ''): ?>
                                <input type="hidden" name="phase" value="<?= h((string) $phase) ?>">
                            <?php endif; ?>
                            <input type="search" name="q" class="form-control border-0" placeholder="Поиск по названию или описанию"
                                   value="<?= h((string) ($query ?? '')) ?>" autocomplete="off" enterkeyhint="search">
                            <button type="submit" class="btn btn-primary px-4">Поиск</button>
                        </div>
                    </form>
                    <div class="iro-tags">
                        <span class="text-muted small align-self-center me-1">По датам:</span>
                        <?php foreach ($calendar_phases as $phaseRow): ?>
                            <?php
                            $code = $phaseRow[0];
                            $name = $phaseRow[1];
                            $qTrim = trim((string) ($query ?? ''));
                            $parts = [];
                            if ($qTrim !== '') {
                                $parts['q'] = $qTrim;
                            }
                            if (($view_mode ?? 'cards') === 'list') {
                                $parts['view'] = 'list';
                            }
                            $parts['phase'] = $code;
                            $phaseHref = '?' . http_build_query($parts);
                            ?>
                            <a href="<?= h($phaseHref) ?>"
                               class="iro-tag iro-home-ajax <?= ($phase ?? '') === $code ? 'is-active' : '' ?>"
                               data-phase="<?= h($code) ?>">
                                <?= h($name) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php
                        $showReset = ($phase ?? '') !== '' || trim((string) ($query ?? '')) !== '' || (($view_mode ?? 'cards') === 'list');
                        $resetHref = (($view_mode ?? 'cards') === 'list') ? '/?view=list' : '/';
                        ?>
                        <a href="<?= h($resetHref) ?>"
                           class="iro-tag iro-home-ajax iro-home-reset<?= $showReset ? '' : ' d-none' ?>"
                           data-phase="">Сбросить</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <aside class="iro-hero-aside iro-hero-aside--photo" aria-label="Иллюстрация">
                        <img src="/assets/img/home-hero-lake.png" class="iro-hero-aside__img" width="1200" height="800" loading="lazy" decoding="async" alt="Горное озеро с отражением неба и лесистых склонов">
                    </aside>
                </div>
            </div>
        </div>
    </section>
</div>

<div id="iro-home-contests-root" class="iro-home-contests-root" aria-live="polite">
    <?php require __DIR__ . '/home/contests_inner.php'; ?>
</div>

<?php
$partners = is_array($partners ?? null) ? $partners : [];
$homeNewsBottomMb = $partners === [] ? ' mb-4' : '';
?>
<?php if (!empty($news)): ?>
    <section class="iro-home-news mt-5 pt-4 border-top border-light<?= h($homeNewsBottomMb) ?>" aria-labelledby="home-news-heading">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 id="home-news-heading" class="iro-section-title mb-0">Новости и объявления</h2>
            <a href="/news" class="btn btn-outline-primary btn-sm rounded-pill px-3">Все материалы</a>
        </div>
        <div class="row g-3">
            <?php foreach ($news as $a): ?>
                <?php
                $teaser = iro_announcement_list_teaser((string) ($a['summary'] ?? ''), (string) ($a['body'] ?? ''));
                $teaserShort = iro_truncate_words($teaser, 22);
                ?>
                <div class="col-md-4">
                    <div class="iro-panel h-100 mb-0 d-flex flex-column">
                        <?php if (!empty($a['is_pinned'])): ?>
                            <span class="badge text-bg-secondary rounded-pill align-self-start mb-2">Закреплено</span>
                        <?php endif; ?>
                        <h3 class="iro-subcard-title">
                            <a href="/news/<?= (int) $a['id'] ?>/" class="text-decoration-none text-body"><?= h((string) $a['title']) ?></a>
                        </h3>
                        <p class="text-muted small mb-2"><?= h(iro_format_datetime_long_ru((string) $a['published_at'])) ?></p>
                        <p class="small text-body-secondary mb-3 flex-grow-1"><?= h($teaserShort) ?></p>
                        <a href="/news/<?= (int) $a['id'] ?>/" class="small mt-auto fw-semibold text-primary text-decoration-none">Подробнее →</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($partners !== []): ?>
    <div class="iro-full-bleed iro-home-partners-bleed mt-4">
        <div class="iro-home-partners__wave-top" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 80" preserveAspectRatio="none" width="100%" height="80" focusable="false">
                <defs>
                    <linearGradient id="iroPartnerWaveTopGrad" x1="0" y1="0" x2="0" y2="1" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#f1f5f9"/>
                        <stop offset="38%" stop-color="#eef4fa"/>
                        <stop offset="100%" stop-color="#f1f5f9"/>
                    </linearGradient>
                </defs>
                <!-- Замкнутый контур: верх прямой (без прозрачной щели), низ — плавная волна -->
                <path fill="url(#iroPartnerWaveTopGrad)" d="M0,0 L1440,0 L1440,46 C1200,62 960,28 720,42 C480,56 240,32 0,50 L0,0 Z"/>
            </svg>
        </div>
        <section class="iro-home-partners" id="home-partners" aria-labelledby="home-partners-heading" data-iro-partners-root>
            <div class="container text-center iro-home-partners__inner">
                <p class="iro-home-partners__kicker" id="home-partners-kicker">Совместная работа над проектами</p>
                <h2 id="home-partners-heading" class="iro-home-partners__title">Создаём большее вместе</h2>
                <div class="iro-home-partners__rule" aria-hidden="true"></div>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 g-lg-4 justify-content-center iro-home-partners__grid">
                    <?php foreach ($partners as $idx => $pr): ?>
                        <?php
                        $partnerUrl = trim((string) ($pr['website_url'] ?? ''));
                        $logo = (string) ($pr['logo_path'] ?? '');
                        $ptitle = (string) ($pr['title'] ?? '');
                        $stagger = min((int) $idx, 11);
                        ?>
                        <div class="col">
                            <?php if ($partnerUrl !== ''): ?>
                                <a href="<?= h($partnerUrl) ?>" class="iro-home-partners__card iro-home-partners__card--link h-100 text-decoration-none text-body" target="_blank" rel="noopener noreferrer" style="--iro-partner-i: <?= (int) $stagger ?>;">
                            <?php else: ?>
                                <div class="iro-home-partners__card h-100" style="--iro-partner-i: <?= (int) $stagger ?>;">
                            <?php endif; ?>
                                <div class="iro-home-partners__logo-wrap">
                                    <img src="<?= h($logo) ?>" class="iro-home-partners__logo" width="280" height="160" loading="lazy" decoding="async" alt="<?= h($ptitle) ?>">
                                </div>
                            <?php if ($partnerUrl !== ''): ?>
                                </a>
                            <?php else: ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <div class="iro-home-partners__wave-bottom" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 72" preserveAspectRatio="none" width="100%" height="72" focusable="false">
                <path fill="var(--iro-navy)" d="M0,44 C240,12 480,68 720,32 C960,-4 1200,52 1440,24 L1440,72 L0,72 Z"/>
            </svg>
        </div>
    </div>
<?php endif; ?>
