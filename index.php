<?php
// test cicd
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';
try {
  $groupContext = hyg_context();
} catch (Throwable $e) {
  hyg_error_page($e);
}
$cats = db()->query('SELECT c.*, COUNT(p.id) product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.active=1 WHERE c.active=1 GROUP BY c.id HAVING product_count>0 ORDER BY c.sort_order,c.id')->fetchAll();
$allProducts = db()->query("SELECT p.*,COALESCE(s.sold_qty,0) sold_qty FROM products p LEFT JOIN (SELECT oi.product_id,SUM(oi.qty) sold_qty FROM order_items oi WHERE oi.product_id IS NOT NULL GROUP BY oi.product_id) s ON s.product_id=p.id WHERE p.active=1 ORDER BY p.sort_order,p.id")->fetchAll();
$productsByCat = [];
foreach ($allProducts as $p)
  $productsByCat[(int) $p['category_id']][] = $p;
$hotProducts = array_values(array_filter($allProducts, fn($p) => !empty($p['is_hot'])));
$standaloneToppings = db()->query('SELECT * FROM toppings WHERE active=1 AND sell_separately=1 ORDER BY sort_order,id')->fetchAll();
$featuredProducts = db()->query("SELECT p.*,COALESCE(s.sold_qty,0) sold_qty FROM products p LEFT JOIN (SELECT oi.product_id,SUM(oi.qty) sold_qty FROM order_items oi WHERE oi.product_id IS NOT NULL GROUP BY oi.product_id) s ON s.product_id=p.id WHERE p.active=1 AND (p.is_new=1 OR p.is_hot=1) ORDER BY p.is_new DESC,p.is_hot DESC,p.sort_order,p.id LIMIT 12")->fetchAll();
$featuredToppings = db()->query('SELECT * FROM toppings WHERE active=1 AND sell_separately=1 AND (is_new=1 OR is_hot=1) ORDER BY is_new DESC,is_hot DESC,sort_order,id LIMIT 12')->fetchAll();
// Home banner slider (admin/banners.php). Inside a group order, banner links keep the group context.
$banners = hbn_active();
if ($groupContext) {
  foreach ($banners as &$slide) {
    if ($slide['url'] !== '') {
      $slide['url'] .= (str_contains($slide['url'], '?') ? '&' : '?') . 'group=' . $groupContext['room']['public_id'];
    }
  }
  unset($slide);
}
$firstKey = $hotProducts ? 'hot' : ($cats ? 'cat-' . (int) $cats[0]['id'] : ($standaloneToppings ? 'standalone' : ''));
front_header(); ?>
<?php if ($banners): ?>
  <link rel="stylesheet" href="<?= e(base_url('assets/css/home-slider.css?v=2')) ?>">
  <!-- Auto-playing banner slider: assets/js/home-slider.js. A single banner shows as a still image. -->
  <section class="home-banner-wrap home-slider" data-home-slider data-interval="<?= hbn_interval() * 1000 ?>"
    aria-roledescription="carousel" aria-label="<?= e(t('homepage_banner')) ?>">
    <div class="home-slider-track" data-slider-track>
      <?php foreach ($banners as $index => $slide): ?>
        <?php
        $alt = t('homepage_banner') . ' ' . ($index + 1);
        $img = '<img src="' . e(base_url('uploads/' . $slide['image'])) . '" alt="' . e($alt) . '"'
          . ($index === 0 ? ' fetchpriority="high"' : ' loading="lazy"') . '>';
        ?>
        <?php if ($slide['url'] !== ''): ?>
          <a class="home-banner home-slide" href="<?= e($slide['url']) ?>" data-slide><?= $img ?></a>
        <?php else: ?>
          <div class="home-banner home-slide" data-slide><?= $img ?></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if (count($banners) > 1): ?>
      <button class="home-slider-arrow is-prev" type="button" data-slider-prev aria-label="<?= e(hbn_t('prev')) ?>">‹</button>
      <button class="home-slider-arrow is-next" type="button" data-slider-next aria-label="<?= e(hbn_t('next')) ?>">›</button>
      <div class="home-slider-dots">
        <?php foreach ($banners as $index => $slide): ?>
          <button type="button" data-slider-dot="<?= $index ?>" aria-label="<?= e(hbn_t('go_to', ['n' => $index + 1])) ?>"<?= $index === 0 ? ' aria-current="true"' : '' ?>></button>
        <?php endforeach; ?>
      </div>
      <script src="<?= e(base_url('assets/js/home-slider.js?v=2')) ?>" defer></script>
    <?php endif; ?>
  </section>
<?php endif; ?>

<!-- close-test-Hai | đóng thẻ liên kế dời xuống footer -->

<!-- <section class="social-media-section" aria-labelledby="social-media-title">
  <div class="social-media-heading">
    <span class="eyebrow"><?= e(t('follow_us')) ?></span>
    <h2 id="social-media-title"><?= e(t('social_media')) ?></h2>
  </div>


  <div class="social-media-links">
    <a class="social-media-link" href="https://www.facebook.com/huiyitea.vn" target="_blank" rel="noopener noreferrer"
      aria-label="Facebook"><span class="social-media-icon social-facebook"
        aria-hidden="true">f</span><span>Facebook</span></a>
    <a class="social-media-link" href="https://www.instagram.com/huiyitea/" target="_blank" rel="noopener noreferrer"
      aria-label="Instagram"><span class="social-media-icon social-instagram" aria-hidden="true"><svg
          viewBox="0 0 24 24" focusable="false">
          <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
          <circle cx="12" cy="12" r="4" />
          <circle cx="17.5" cy="6.7" r="1" class="fill-dot" />
        </svg></span><span>Instagram</span></a>
    <a class="social-media-link" href="https://www.threads.com/@huiyitea" target="_blank" rel="noopener noreferrer"
      aria-label="Threads"><span class="social-media-icon social-threads"
        aria-hidden="true">@</span><span>Threads</span></a>
    <a class="social-media-link" href="https://www.tiktok.com/@huiyitea" target="_blank" rel="noopener noreferrer"
      aria-label="TikTok"><span class="social-media-icon social-tiktok" aria-hidden="true"><svg viewBox="0 0 24 24"
          focusable="false">
          <path
            d="M14.2 4.2v9.1a4.45 4.45 0 1 1-3.7-4.38v3.02a1.65 1.65 0 1 0 .9 1.47V4.2h2.8Zm0 0c.55 2.1 1.85 3.42 4.1 3.8v2.77a7.3 7.3 0 0 1-4.1-1.42" />
        </svg></span><span>TikTok</span></a>
    <a class="social-media-link" href="https://www.youtube.com/@huiyitea" target="_blank" rel="noopener noreferrer"
      aria-label="YouTube"><span class="social-media-icon social-youtube" aria-hidden="true"><svg viewBox="0 0 24 24"
          focusable="false">
          <rect x="2.7" y="5.4" width="18.6" height="13.2" rx="4" />
          <path class="social-youtube-play" d="m10.1 9 5.1 3-5.1 3Z" />
        </svg></span><span>YouTube</span></a>
  </div>
</section> -->

<?php if ($featuredProducts || $featuredToppings): ?>
  <section class="featured-section" id="featured">
    <div class="section-title-row featured-title">
      <div><span class="eyebrow"><?= e(t('featured_eyebrow')) ?></span>
        <h2><?= e(t('new_hot_recommendations')) ?></h2>
      </div><span class="section-count"><?= count($featuredProducts) + count($featuredToppings) ?></span>
    </div>
    <div class="featured-scroller">
      <?php foreach ($featuredProducts as $p):
        $pn = localized_name($p); ?><a class="featured-card"
          href="<?= e(hyg_url('product.php?id=' . $p['id'])) ?>">
          <div class="featured-image"><?php if ($p['image']): ?><img src="<?= e(base_url('uploads/' . $p['image'])) ?>"
                alt="<?= e($pn) ?>" loading="lazy"><?php else: ?>
              <div class="placeholder">DRINK</div><?php endif; ?>
            <div class="badge-stack"><?php if (!empty($p['is_new'])): ?><span
                  class="product-badge new"><?= e(t('new_badge')) ?></span><?php endif; ?></div>
            <?php if (!empty($p['is_hot'])): ?><span class="hot-corner-badge" title="<?= e(t('hot_badge')) ?>"><img
                  class="hot-corner-thumb" src="<?= e(base_url('assets/icons/thumb-up.png?v=18.12')) ?>"
                  alt="<?= e(t('hot_badge')) ?>"></span><?php endif; ?>
          </div>
          <div class="featured-info">
            <h3><?= e($pn) ?></h3><strong class="product-price-text"><?php if (product_has_sizes($p)): ?><span
                  class="size-price-row"><span class="size-price-label">L</span><span
                    class="size-price-value"><?= e(currency()) ?><?= money($p['price_l'] ?? 0) ?></span></span><span
                  class="size-price-row"><span class="size-price-label">XL</span><span
                    class="size-price-value"><?= e(currency()) ?><?= money($p['price_xl'] ?? 0) ?></span></span><?php else: ?><?= e(currency()) ?><?= money($p['price'] ?? 0) ?><?php endif; ?></strong><?php if (show_product_sales_count()): ?><small
                class="sold-count"><?= e(t('sold_cups', ['qty' => number_format((int) $p['sold_qty'])])) ?></small><?php endif; ?>
          </div>
        </a><?php endforeach; ?>
      <?php foreach ($featuredToppings as $top):
        $tn = localized_name($top); ?><a class="featured-card"
          href="<?= e(hyg_url('topping.php?id=' . $top['id'])) ?>">
          <div class="featured-image"><?php if (!empty($top['image'])): ?><img
                src="<?= e(base_url('uploads/' . $top['image'])) ?>" alt="<?= e($tn) ?>" loading="lazy"><?php else: ?>
              <div class="placeholder">TOPPING</div><?php endif; ?>
            <div class="badge-stack"><?php if (!empty($top['is_new'])): ?><span
                  class="product-badge new"><?= e(t('new_badge')) ?></span><?php endif; ?></div>
            <?php if (!empty($top['is_hot'])): ?><span class="hot-corner-badge" title="<?= e(t('hot_badge')) ?>"><img
                  class="hot-corner-thumb" src="<?= e(base_url('assets/icons/thumb-up.png?v=18.12')) ?>"
                  alt="<?= e(t('hot_badge')) ?>"></span><?php endif; ?>
          </div>
          <div class="featured-info">
            <h3><?= e($tn) ?></h3><strong
              class="product-price-text"><?= e(currency()) ?><?= money($top['retail_price']) ?></strong>
          </div>
        </a><?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<!-- close-test-Hai | Đóng banner làm tối giản màn hình -->
<!-- <section class="hero">
  <div class="hero-content"><span class="eyebrow"><?= e(t('mobile_order')) ?></span>
    <h1><?= e(t('hero_title')) ?></h1>
    <p><?= e(t('hero_desc')) ?></p>
  </div>
  <div class="hero-orb" aria-hidden="true"></div>
</section> -->

<?php if (!$groupContext): ?>
  <?php $inGroupOrder = hyg_has_live_group(); ?>
  <section class="hyg-entry hyg-entry-highlight<?= $inGroupOrder ? ' is-locked' : '' ?>">
    <div><strong><?= e(gt('entry_title')) ?></strong>
      <p><?= e(gt('entry_desc')) ?></p>
    </div>
    <?php if ($inGroupOrder): ?><button type="button" class="btn primary hyg-entry-cta" disabled aria-disabled="true"><?php else: ?><a class="btn primary hyg-entry-cta" href="<?= e(hym_login_gate_url('group')) ?>"><?php endif; ?>
      <!-- people icon -->
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="9" cy="8" r="3" />
        <path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5a3 3 0 0 1 0 6M21 20v-1a6 6 0 0 0-4-5.6" />
      </svg>
      <span><?= e(gt('entry_cta')) ?></span>
      <span class="hyg-entry-arrow" aria-hidden="true">→</span>
    <?php if ($inGroupOrder): ?></button><?php else: ?></a><?php endif; ?>
  </section><?php endif; ?>
<?php if ($firstKey !== ''): ?>
  <section class="catalog-shell" data-catalog>
    <aside class="catalog-sidebar">
      <div class="catalog-sidebar-head"><?= e(t('categories')) ?></div>
      <div class="catalog-category-list">
        <?php if ($hotProducts): ?><button type="button" class="catalog-category-btn <?= $firstKey === 'hot' ? 'active' : '' ?>"
            data-menu-category="hot"><span><?= e(t('hot_products')) ?></span><b><?= count($hotProducts) ?></b></button><?php endif; ?>
        <?php foreach ($cats as $cat):
          $key = 'cat-' . (int) $cat['id']; ?><button type="button"
            class="catalog-category-btn <?= $key === $firstKey ? 'active' : '' ?>"
            data-menu-category="<?= e($key) ?>"><span><?= e(localized_name($cat)) ?></span><b><?= count($productsByCat[(int) $cat['id']] ?? []) ?></b></button><?php endforeach; ?>
        <?php if ($standaloneToppings): ?><button type="button"
            class="catalog-category-btn <?= $firstKey === 'standalone' ? 'active' : '' ?>"
            data-menu-category="standalone"><span><?= e(t('catalog_topping_category')) ?></span><b><?= count($standaloneToppings) ?></b></button><?php endif; ?>
      </div>
    </aside>
    <div class="catalog-main">
      <?php if ($hotProducts): ?>
        <section class="catalog-products-panel <?= $firstKey === 'hot' ? 'active' : '' ?>" data-menu-panel="hot">
          <div class="catalog-panel-head">
            <h2><?= e(t('hot_products')) ?></h2><span><?= count($hotProducts) ?></span>
          </div>
          <div class="product-grid catalog-product-grid">
            <?php foreach ($hotProducts as $p):
              $pn = localized_name($p); ?><a class="product-card"
                href="<?= e(hyg_url('product.php?id=' . $p['id'])) ?>">
                <div class="product-img"><?php if ($p['image']): ?><img src="<?= e(base_url('uploads/' . $p['image'])) ?>"
                      alt="<?= e($pn) ?>" loading="lazy"><?php else: ?>
                    <div class="placeholder">DRINK</div><?php endif; ?>
                  <div class="badge-stack"><?php if (!empty($p['is_new'])): ?><span
                        class="product-badge new"><?= e(t('new_badge')) ?></span><?php endif; ?></div><span
                    class="hot-corner-badge" title="<?= e(t('hot_badge')) ?>"><img class="hot-corner-thumb"
                      src="<?= e(base_url('assets/icons/thumb-up.png?v=18.12')) ?>" alt="<?= e(t('hot_badge')) ?>"></span>
                </div>
                <div class="product-info">
                  <h3><?= e($pn) ?></h3>
                  <div class="product-meta"><strong class="product-price-text"><?php if (product_has_sizes($p)): ?><span
                          class="size-price-row"><span class="size-price-label">L</span><span
                            class="size-price-value"><?= e(currency()) ?><?= money($p['price_l'] ?? 0) ?></span></span><span
                          class="size-price-row"><span class="size-price-label">XL</span><span
                            class="size-price-value"><?= e(currency()) ?><?= money($p['price_xl'] ?? 0) ?></span></span><?php else: ?><?= e(currency()) ?><?= money($p['price'] ?? 0) ?><?php endif; ?></strong><span
                      class="product-arrow" aria-hidden="true">+</span></div><?php if (show_product_sales_count()): ?><small
                      class="sold-count"><?= e(t('sold_cups', ['qty' => number_format((int) $p['sold_qty'])])) ?></small><?php endif; ?>
                </div>
              </a><?php endforeach; ?>
          </div>
        </section><?php endif; ?>
      <?php foreach ($cats as $cat):
        $key = 'cat-' . (int) $cat['id'];
        $products = $productsByCat[(int) $cat['id']] ?? []; ?>
        <section class="catalog-products-panel <?= $key === $firstKey ? 'active' : '' ?>" data-menu-panel="<?= e($key) ?>">
          <div class="catalog-panel-head">
            <h2><?= e(localized_name($cat)) ?></h2><span><?= count($products) ?></span>
          </div>
          <div class="product-grid catalog-product-grid">
            <?php foreach ($products as $p):
              $pn = localized_name($p); ?><a class="product-card"
                href="<?= e(hyg_url('product.php?id=' . $p['id'])) ?>">
                <div class="product-img"><?php if ($p['image']): ?><img src="<?= e(base_url('uploads/' . $p['image'])) ?>"
                      alt="<?= e($pn) ?>" loading="lazy"><?php else: ?>
                    <div class="placeholder">DRINK</div><?php endif; ?>
                  <div class="badge-stack"><?php if (!empty($p['is_new'])): ?><span
                        class="product-badge new"><?= e(t('new_badge')) ?></span><?php endif; ?></div>
                  <?php if (!empty($p['is_hot'])): ?><span class="hot-corner-badge" title="<?= e(t('hot_badge')) ?>"><img
                        class="hot-corner-thumb" src="<?= e(base_url('assets/icons/thumb-up.png?v=18.12')) ?>"
                        alt="<?= e(t('hot_badge')) ?>"></span><?php endif; ?>
                </div>
                <div class="product-info">
                  <h3><?= e($pn) ?></h3>
                  <div class="product-meta"><strong class="product-price-text"><?php if (product_has_sizes($p)): ?><span
                          class="size-price-row"><span class="size-price-label">L</span><span
                            class="size-price-value"><?= e(currency()) ?><?= money($p['price_l'] ?? 0) ?></span></span><span
                          class="size-price-row"><span class="size-price-label">XL</span><span
                            class="size-price-value"><?= e(currency()) ?><?= money($p['price_xl'] ?? 0) ?></span></span><?php else: ?><?= e(currency()) ?><?= money($p['price'] ?? 0) ?><?php endif; ?></strong><span
                      class="product-arrow" aria-hidden="true">+</span></div><?php if (show_product_sales_count()): ?><small
                      class="sold-count"><?= e(t('sold_cups', ['qty' => number_format((int) $p['sold_qty'])])) ?></small><?php endif; ?>
                </div>
              </a><?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
      <?php if ($standaloneToppings): ?>
        <section class="catalog-products-panel <?= $firstKey === 'standalone' ? 'active' : '' ?>" data-menu-panel="standalone">
          <div class="catalog-panel-head">
            <h2><?= e(t('catalog_topping_category')) ?></h2><span><?= count($standaloneToppings) ?></span>
          </div>
          <div class="product-grid catalog-product-grid">
            <?php foreach ($standaloneToppings as $top):
              $tn = localized_name($top); ?><a class="product-card"
                href="<?= e(hyg_url('topping.php?id=' . $top['id'])) ?>">
                <div class="product-img"><?php if (!empty($top['image'])): ?><img
                      src="<?= e(base_url('uploads/' . $top['image'])) ?>" alt="<?= e($tn) ?>" loading="lazy"><?php else: ?>
                    <div class="placeholder">TOPPING</div><?php endif; ?>
                  <div class="badge-stack"><?php if (!empty($top['is_new'])): ?><span
                        class="product-badge new"><?= e(t('new_badge')) ?></span><?php endif; ?></div>
                  <?php if (!empty($top['is_hot'])): ?><span class="hot-corner-badge" title="<?= e(t('hot_badge')) ?>"><img
                        class="hot-corner-thumb" src="<?= e(base_url('assets/icons/thumb-up.png?v=18.12')) ?>"
                        alt="<?= e(t('hot_badge')) ?>"></span><?php endif; ?>
                </div>
                <div class="product-info">
                  <h3><?= e($tn) ?></h3>
                  <div class="product-meta"><strong
                      class="product-price-text"><?= e(currency()) ?><?= money($top['retail_price']) ?></strong><span
                      class="product-arrow" aria-hidden="true">+</span></div>
                </div>
              </a><?php endforeach; ?></div>
        </section><?php endif; ?>
    </div>
  </section>
<?php endif; ?>
<?php front_footer(); ?>