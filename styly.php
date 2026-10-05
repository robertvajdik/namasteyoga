<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

ny_render_header(t('styly.title'), 'styly', [
    'description' => t('styly.meta.description')
]);

$toggleMore = e(t('styly.toggle.more'));
$toggleLess = e(t('styly.toggle.less'));
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('styly.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('styly.hero.title')) ?></h1>
    <p class="page-lead"><?= t('styly.hero.lead') ?></p>
</section>

<section class="styly-section">
    <div class="styly-section-head">
        <div class="eyebrow"><?= t('styly.sec1.eyebrow') ?></div>
        <h2 class="section-h"><?= e(t('styly.sec1.title')) ?></h2>
        <p class="styly-section-lead"><?= e(t('styly.sec1.lead')) ?></p>
    </div>
    <div class="styly-list">
        <article class="styly-item">
            <h3><?= t('styly.restorative.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.restorative.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.restorative.p1')) ?></p>
                    <p><?= e(t('styly.restorative.p2')) ?></p>
                    <p><?= e(t('styly.restorative.p3')) ?></p>
                    <h4><?= e(t('styly.restorative.whom')) ?></h4>
                    <p><?= e(t('styly.restorative.whom.p1')) ?></p>
                    <p><?= e(t('styly.restorative.whom.p2')) ?></p>
                    <p class="styly-namaste"><?= e(t('styly.namaste')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.yinsoul.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.yinsoul.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= t('styly.yinsoul.p1') ?></p>
                    <p><?= e(t('styly.yinsoul.p2')) ?></p>
                    <h4><?= t('styly.yinsoul.whom') ?></h4>
                    <p><?= e(t('styly.yinsoul.whom.p1')) ?></p>
                    <p><?= e(t('styly.yinsoul.whom.p2')) ?></p>
                    <p class="styly-namaste"><?= e(t('styly.namaste')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= e(t('styly.zaklady.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.zaklady.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.zaklady.p1')) ?></p>
                    <p><?= e(t('styly.zaklady.p2')) ?></p>
                    <h4><?= e(t('styly.zaklady.learn')) ?></h4>
                    <ul class="styly-bullets">
                        <li><?= e(t('styly.zaklady.b1')) ?></li>
                        <li><?= e(t('styly.zaklady.b2')) ?></li>
                        <li><?= e(t('styly.zaklady.b3')) ?></li>
                        <li><?= e(t('styly.zaklady.b4')) ?></li>
                        <li><?= e(t('styly.zaklady.b5')) ?></li>
                        <li><?= e(t('styly.zaklady.b6')) ?></li>
                    </ul>
                    <p><?= e(t('styly.zaklady.p3')) ?></p>
                    <p><?= e(t('styly.zaklady.p4')) ?></p>
                    <p><?= e(t('styly.zaklady.p5')) ?></p>
                    <p class="styly-namaste"><?= e(t('styly.namaste')) ?></p>
                </div>
            </details>
        </article>
    </div>
</section>

<section class="styly-section">
    <div class="styly-section-head">
        <div class="eyebrow"><?= t('styly.sec2.eyebrow') ?></div>
        <h2 class="section-h"><?= e(t('styly.sec2.title')) ?></h2>
        <p class="styly-section-lead"><?= e(t('styly.sec2.lead')) ?></p>
    </div>
    <div class="styly-list">
        <article class="styly-item">
            <h3><?= t('styly.power.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.power.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.power.p1')) ?></p>
                    <p><?= e(t('styly.power.p2')) ?></p>
                    <h4><?= e(t('styly.power.whom')) ?></h4>
                    <p><?= e(t('styly.power.whom.p1')) ?></p>
                    <p class="styly-namaste"><?= e(t('styly.namaste')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.core.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.core.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.core.p1')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.pilatesSoft.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.pilatesSoft.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= t('styly.pilatesSoft.p1') ?></p>
                    <p><?= e(t('styly.pilatesSoft.p2')) ?></p>
                    <p><?= e(t('styly.pilatesSoft.p3')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.pilatesFull.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.pilatesFull.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.pilatesFull.p1')) ?></p>
                    <p><?= e(t('styly.pilatesFull.p2')) ?></p>
                    <p><?= e(t('styly.pilatesFull.p3')) ?></p>
                    <p><?= e(t('styly.pilatesFull.p4')) ?></p>
                    <p><?= e(t('styly.pilatesFull.p5')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= e(t('styly.vinyasa.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.vinyasa.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.vinyasa.p1')) ?></p>
                </div>
            </details>
        </article>
    </div>
</section>

<section class="styly-section">
    <div class="styly-section-head">
        <div class="eyebrow"><?= t('styly.sec3.eyebrow') ?></div>
        <h2 class="section-h"><?= e(t('styly.sec3.title')) ?></h2>
        <p class="styly-section-lead"><?= e(t('styly.sec3.lead')) ?></p>
    </div>
    <div class="styly-list">
        <article class="styly-item">
            <h3><?= t('styly.panevni.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.panevni.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.panevni.p1')) ?></p>
                    <p><?= e(t('styly.panevni.p2')) ?></p>
                    <p><?= e(t('styly.panevni.p3')) ?></p>
                    <p><?= e(t('styly.panevni.p4')) ?></p>
                    <h4><?= e(t('styly.panevni.whom')) ?></h4>
                    <p><?= e(t('styly.panevni.whom.p1')) ?></p>
                    <p><?= e(t('styly.panevni.whom.p2')) ?></p>
                    <h4><?= e(t('styly.panevni.proc')) ?></h4>
                    <p><?= e(t('styly.panevni.proc.p')) ?></p>
                    <ul class="styly-bullets">
                        <li><?= e(t('styly.panevni.b1')) ?></li>
                        <li><?= e(t('styly.panevni.b2')) ?></li>
                        <li><?= e(t('styly.panevni.b3')) ?></li>
                        <li><?= e(t('styly.panevni.b4')) ?></li>
                        <li><?= e(t('styly.panevni.b5')) ?></li>
                        <li><?= e(t('styly.panevni.b6')) ?></li>
                        <li><?= e(t('styly.panevni.b7')) ?></li>
                    </ul>
                    <p><?= e(t('styly.panevni.p5')) ?></p>
                    <p class="styly-namaste"><?= e(t('styly.namaste')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= e(t('styly.tehotne.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.tehotne.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.tehotne.p1')) ?></p>
                    <p><?= e(t('styly.tehotne.p2')) ?></p>
                    <p><?= e(t('styly.tehotne.p3')) ?></p>
                    <p><?= e(t('styly.tehotne.p4')) ?></p>
                    <h4><?= e(t('styly.tehotne.whom')) ?></h4>
                    <p><?= e(t('styly.tehotne.whom.p1')) ?></p>
                    <p><?= e(t('styly.tehotne.whom.p2')) ?></p>
                    <p class="styly-note"><?= e(t('styly.tehotne.note')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.diastaza.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.diastaza.tag')) ?></p>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.hormonalni.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.hormonalni.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.hormonalni.p1')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.intuitivni.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.intuitivni.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.intuitivni.p1')) ?></p>
                    <h4><?= e(t('styly.intuitivni.obsah')) ?></h4>
                    <ul class="styly-bullets">
                        <li><?= e(t('styly.intuitivni.b1')) ?></li>
                        <li><?= e(t('styly.intuitivni.b2')) ?></li>
                        <li><?= e(t('styly.intuitivni.b3')) ?></li>
                        <li><?= e(t('styly.intuitivni.b4')) ?></li>
                        <li><?= e(t('styly.intuitivni.b5')) ?></li>
                        <li><?= e(t('styly.intuitivni.b6')) ?></li>
                        <li><?= e(t('styly.intuitivni.b7')) ?></li>
                        <li><?= e(t('styly.intuitivni.b8')) ?></li>
                        <li><?= e(t('styly.intuitivni.b9')) ?></li>
                    </ul>
                    <p><?= e(t('styly.intuitivni.p2')) ?></p>
                </div>
            </details>
        </article>
    </div>
</section>

<section class="styly-section">
    <div class="styly-section-head">
        <div class="eyebrow"><?= t('styly.sec4.eyebrow') ?></div>
        <h2 class="section-h"><?= e(t('styly.sec4.title')) ?></h2>
        <p class="styly-section-lead"><?= e(t('styly.sec4.lead')) ?></p>
    </div>
    <div class="styly-list">
        <article class="styly-item">
            <h3><?= t('styly.fly.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.fly.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.fly.p1')) ?></p>
                    <p><?= e(t('styly.fly.p2')) ?></p>
                    <h4><?= e(t('styly.fly.whom')) ?></h4>
                    <p><?= e(t('styly.fly.whom.p1')) ?></p>
                    <p><?= e(t('styly.fly.whom.p2')) ?></p>
                    <p class="styly-namaste"><?= e(t('styly.namaste')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.wall.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.wall.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.wall.p1')) ?></p>
                    <p><?= t('styly.wall.flow') ?></p>
                    <p><?= t('styly.wall.yin') ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.blacklight.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.blacklight.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= t('styly.blacklight.p1') ?></p>
                    <p><?= e(t('styly.blacklight.p2')) ?></p>
                    <p><?= e(t('styly.blacklight.p3')) ?></p>
                    <p><?= e(t('styly.blacklight.p4')) ?></p>
                    <p><?= e(t('styly.blacklight.p5')) ?></p>
                    <p><?= e(t('styly.blacklight.p6')) ?></p>
                    <p><?= e(t('styly.blacklight.p7')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.parova.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.parova.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.parova.p1')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= t('styly.cakra.title') ?></h3>
            <p class="styly-tag"><?= e(t('styly.cakra.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.cakra.p1')) ?></p>
                </div>
            </details>
        </article>
    </div>
</section>

<section class="styly-section">
    <div class="styly-section-head">
        <div class="eyebrow"><?= t('styly.sec5.eyebrow') ?></div>
        <h2 class="section-h"><?= e(t('styly.sec5.title')) ?></h2>
    </div>
    <div class="styly-list">
        <article class="styly-item">
            <h3><?= e(t('styly.deti.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.deti.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.deti.p1')) ?></p>
                    <p><?= e(t('styly.deti.p2')) ?></p>
                    <p><?= e(t('styly.deti.p3')) ?></p>
                    <h4><?= e(t('styly.deti.proc')) ?></h4>
                    <ul class="styly-bullets">
                        <li><?= e(t('styly.deti.b1')) ?></li>
                        <li><?= e(t('styly.deti.b2')) ?></li>
                        <li><?= e(t('styly.deti.b3')) ?></li>
                        <li><?= e(t('styly.deti.b4')) ?></li>
                        <li><?= e(t('styly.deti.b5')) ?></li>
                        <li><?= e(t('styly.deti.b6')) ?></li>
                        <li><?= e(t('styly.deti.b7')) ?></li>
                    </ul>
                    <p><?= e(t('styly.deti.p4')) ?></p>
                    <p class="styly-namaste"><?= e(t('styly.namaste')) ?></p>
                </div>
            </details>
        </article>
    </div>
</section>

<section class="styly-section">
    <div class="styly-section-head">
        <div class="eyebrow"><?= t('styly.sec6.eyebrow') ?></div>
        <h2 class="section-h"><?= e(t('styly.sec6.title')) ?></h2>
    </div>
    <div class="styly-list">
        <article class="styly-item">
            <h3><?= e(t('styly.autogenni.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.autogenni.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.autogenni.p1')) ?></p>
                    <p><?= e(t('styly.autogenni.p2')) ?></p>
                    <p><?= e(t('styly.autogenni.p3')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= e(t('styly.meditace.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.meditace.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.meditace.p1')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= e(t('styly.mindfulness.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.mindfulness.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <p><?= e(t('styly.mindfulness.p1')) ?></p>
                </div>
            </details>
        </article>

        <article class="styly-item">
            <h3><?= e(t('styly.taichi.title')) ?></h3>
            <p class="styly-tag"><?= e(t('styly.taichi.tag')) ?></p>
            <details class="styly-details">
                <summary class="styly-toggle" data-more="<?= $toggleMore ?>" data-less="<?= $toggleLess ?>"></summary>
                <div class="styly-body">
                    <blockquote class="styly-quote">
                        <?= e(t('styly.taichi.quote1')) ?>
                        <cite><?= e(t('styly.taichi.quote1.cite')) ?></cite>
                    </blockquote>
                    <p><?= e(t('styly.taichi.p1')) ?></p>
                    <blockquote class="styly-quote styly-quote--sm">
                        <?= e(t('styly.taichi.quote2')) ?>
                        <cite><?= e(t('styly.taichi.quote2.cite')) ?></cite>
                    </blockquote>
                </div>
            </details>
        </article>
    </div>
</section>

<section class="cta-band">
    <div class="cta-inner">
        <h2><?= e(t('styly.cta.title')) ?></h2>
        <p><?= e(t('styly.cta.lead')) ?></p>
        <a class="btn btn-primary btn-lg" href="rezervace.php"><?= e(t('styly.cta.button')) ?></a>
    </div>
</section>

<?php ny_render_footer();
