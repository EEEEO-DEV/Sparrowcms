<section id="comments" style="margin-top:24px">
  <h3>评论</h3>
  <?php $comments = new \Sparrow\Compat\Typecho\Widget\Comments($this->themePath, (int)($this->context['id'] ?? 0)); ?>
  <?php if ($comments->count() === 0): ?>
    <p>暂无评论。</p>
  <?php else: ?>
    <?php while($comments->have()): $comments->next(); ?>
      <article id="comment-<?php $comments->theId(); ?>" style="background:#fff;border-radius:12px;padding:12px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.05);display:flex;gap:12px">
        <div><?php $comments->avatar(40); ?></div>
        <div>
          <div class="muted"><?php $comments->authorLink(); ?> • <?php $comments->date('Y-m-d H:i'); ?> • <a href="<?php $comments->permalink(); ?>">#</a></div>
          <div><?php $comments->content(); ?></div>
        </div>
      </article>
    <?php endwhile; ?>
  <?php endif; ?>
</section>

<section style="margin-top: 16px;">
  <h3>发表评论</h3>
  <form action="/comment" method="post">
    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($_SESSION['_token'] ?? '', ENT_QUOTES); ?>">
    <input type="hidden" name="post_id" value="<?php echo (int)($this->context['id'] ?? 0); ?>">
    <p><input style="width:100%;padding:10px" type="text" name="author" placeholder="昵称" required></p>
    <p><input style="width:100%;padding:10px" type="email" name="email" placeholder="邮箱（可选）"></p>
    <p><textarea style="width:100%;padding:10px" name="content" rows="4" placeholder="评论内容" required></textarea></p>
    <p><button type="submit">提交评论</button></p>
  </form>
</section>
