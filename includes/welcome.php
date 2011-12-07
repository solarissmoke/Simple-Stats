<?php page_head(); ?>

<h2 id="title" class="grid16"><?php echo $i18n->hsc( 'welcome', 'title' ); ?></h2>
<div id="main" class="grid16">
<div id="side" class="grid4"></div>
<div id="content" class="grid12">
<div class="grid12">
<p class="first"><?php echo $i18n->hsc( 'welcome', 'p1' ); ?></p>
<p><?php echo $i18n->hsc( 'welcome', 'p2' ); ?></p>
<p><?php echo $i18n->hsc( 'welcome', 'p3' ); ?></p>
<p><?php echo $i18n->hsc( 'welcome', 'p4' ); ?></p>
<p><?php echo $i18n->hsc( 'welcome', 'p5' ); ?></p>

<h3><?php echo $i18n->hsc( 'welcome', 'javascript' ); ?></h3>
<p><?php echo $i18n->hsc( 'welcome', 'javascript_text' ); ?></p>
<pre>&lt;script type="text/javascript" src="<?php echo dirname( $_SERVER['SCRIPT_NAME'] ); ?>/?js"&gt;&lt;/script&gt;</pre>

<h3><?php echo $i18n->hsc( 'welcome', 'php' ); ?></h3>
<p><?php echo $i18n->hsc( 'welcome', 'php_text1' ); ?></p>
<pre>&lt;?php
@include_once( $_SERVER['DOCUMENT_ROOT'].'<?php echo dirname( $_SERVER['SCRIPT_NAME'] ); ?>/stats_include.php' );
?&gt;</pre>
<pre>&lt;?php
@include_once( '<?php echo dirname( dirname( __FILE__ ) ); ?>/stats_include.php' );
?&gt;</pre>
<p><?php echo $i18n->hsc( 'welcome', 'php_text2' ); ?></p>

</div></div></div>

<?php

page_foot();