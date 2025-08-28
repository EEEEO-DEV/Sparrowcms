<?php $this->need('header.php'); ?>
<?php while($this->have()): $this->next(); ?>
  <article>
    <h1><?php $this->title(); ?></h1>
    <div class="muted"><?php $this->date('Y-m-d H:i'); ?> • 分类：<?php $this->categories('，'); ?> • 标签：<?php $this->tags('，'); ?></div>
    <div style="margin-top:12px"><?php $this->content(); ?></div>
  </article>
<?php endwhile; ?>
<?php $this->need('comments.php'); ?>
<?php $this->need('footer.php'); ?>
