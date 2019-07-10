<div class="carousel">
	<div class="carousel-photo-stage" style="background-image: url('http://134.209.23.223:4002/ob/images/<?php echo (isset($listing->item->images)) ? $listing->item->images[0]->large : ''; ?>');">
		<div class="carousel-back-btn"></div>
		<div class="carousel-next-btn"></div>
	</div>
	<div class="carousel-thumb-bar"></div>
	<div class="carousel-thumbs">
		<?php foreach($listing->item->images as $image) {  ?>
		<div style="display:table-cell;	padding-right:10px;position:relative;">
		<div class="carousel-thumb" data-hash="<?=$image->large?>" style="background-image: url('http://134.209.23.223:4002/ob/images/<?=$image->tiny?>');"></div>
		</div>
		<?php } ?>
	</div>
</div>

