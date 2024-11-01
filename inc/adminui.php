<div class="wrap shopp imagetools">

	<div class="icon32"> </div>
	<h2><?php _e('Image Tools', 'shoppimagetools'); ?></h2>


	<p>
		<?php _e('Shopp Image Tools can move product images from the database to the filesystem, potentially increasing performance '
			.'within some hosting environments. It can also further improve performance by making images directly accessible (direct '
			.'mode). Before you run the conversion or cleanup tools, however, <strong>please be safe and back up</strong>.', 'shoppimagetools'); ?>
	</p>

	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e('Type of Storage', 'shoppimagetools'); ?></th>
				<th><?php _e('Number of Images', 'shoppimagetools'); ?></th>
			</tr>
		</thead>
		<tbody>
		    <tr>
			    <td><?php _e('Database', 'shoppimagetools'); ?></td>
			    <td class="db-total"><?php echo esc_html($totals['db']); ?></td>
		    </tr>
		    <tr>
			    <td><?php _e('File system', 'shoppimagetools'); ?></td>
			    <td class="fs-total"><?php echo esc_html($totals['fs']); ?></td>
		    </tr>
		    <tr>
			    <td><?php _e('Other', 'shoppimagetools'); ?></td>
			    <td class="other-total"><?php echo esc_html($totals['other']); ?></td>
		    </tr>
		</tbody>
	</table>

	<form action="<?php echo $conversionAction; ?>" method="post">

		<div class="toolhelp">
			<p> <?php _e('Enabling smart cleanup allows the Conversion Tool to remove each image from the database (once successfully converted). '
				.'If you do not enable smart cleanup initially you can use the Orphan Cleanup Tool later on &ndash; this is also useful if '
				.'you have orphaned images in the database resulting from mistakes such as uploading an image and forgetting to save it.', 'shoppimagetools') ?>	</p>
		</div>

		<div class="tools">
			<p class="control directmode">
				<?php if ($directMode === true): ?>
					<a href="<?php echo $directModeAction; ?>" class="button-secondary">
						<?php _e('Disable Direct Mode', 'shoppimagetools') ?>
					</a> <br/>
					<img src="<?php echo $tickIcon; ?>" alt="<?php _e('Tick mark icon: direct mode on', 'shoppimagetools'); ?>"  title="<? _e('Direct mode is ON', 'shoppimagetools'); ?>" class="directindicator" />
					<?php _e('Direct mode is currently enabled!', 'shoppimagetools') ?>
				<?php else: ?>
					<a href="<?php echo $directModeAction; ?>" class="button-secondary">
						<?php _e('Enable Direct Mode', 'shoppimagetools'); ?>
					</a> <br/>
					<img src="<?php echo $crossIcon; ?>" alt="<?php _e('Cross icon: direct mode off', 'shoppimagetools'); ?>" title="<? _e('Direct mode is OFF', 'shoppimagetools'); ?>" class="directindicator" />
					<?php _e('You have not enabled direct mode.', 'shoppimagetools') ?>
				<?php endif; ?>
			</p>

			<p class="control converter"> <input type="submit" name="converter" id="converter" value="<?php _e('Run Conversion Tool', 'shoppimagetools'); ?>" class="button-secondary" /> <br/>
				<span class="control-group"> <input type="checkbox" name="smartcleanup" id="smartcleanup" value="1" />
				<label><?php _e('Enable smart cleanup', 'shoppimagetools') ?></label> </span> </p>

			<p class="control orphans">
				<input type="submit" name="orphancleanup" id="orphancleanup" value="<?php _e('Run Orphan Cleanup Tool', 'shoppimagetools'); ?>" class="button-secondary" /></p>
		</div>

	</form>

	<input type="hidden" name="checkstring" id="checkstring" value="<?php echo wp_create_nonce('ajaxconversionop'); ?>" />

	<?php if (isset($conversionLog)): ?>

		<div id="conversionlog">
			<?php echo $conversionLog; ?>
		</div>

	<?php endif; ?>

</div>