<?php
/**
 * The header for the output table.
 *
 * @global \GV\Template_Context $gravityview
 */
?>
<?php gravityview_before( $gravityview ); ?>
<div class="<?php gv_container_class('gv-table-view gv-table-container gv-table-multiple-container'); ?>">
<table class="gv-table-view">
	<thead>
		<?php gravityview_header(); ?>
		<tr>
			<?php $gravityview->template->the_columns(); ?>
		</tr>
	</thead>
