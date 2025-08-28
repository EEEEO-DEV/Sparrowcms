<?php $this->need('header.php'); ?>
<?php while($this->have()): $this->next(); ?>
  <article>
    <h2><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h2>
    <div class="muted"><?php $this->date('Y-m-d H:i'); ?> • 分类：<?php $this->categories('，'); ?> • 标签：<?php $this->tags('，'); ?></div>
    <p><?php $this->excerpt(180); ?></p>
  </article>
<?php endwhile; ?>
<?php $this->need('footer.php'); ?>
