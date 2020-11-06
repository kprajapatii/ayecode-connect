<?php
global $ac_site_args;
?>
<div class="col mb-4" >
	<div class="card h-100 p-0 m-0 mw-100  " data-demo="<?php echo esc_attr($ac_site_args->slug);?>">
		<div class="card-img-top overflow-hidden position-relative ">
			<div class="geodir-post-slider bsui sdel-b2db03d5"><div class=" geodir-image-container geodir-image-sizes-medium_large   ">
					<div class="geodir-images geodir-images-n-1 geodir-images-image carousel-inner  ">
						<div class="carousel-item  active">
							<a href="https://wpgeo.directory/<?php echo esc_attr($ac_site_args->slug); ?>" onclick="ac_preview_site(this);return false;" class="embed-has-action embed-responsive embed-responsive-16by9 d-block">
								<img src="https://wordpress.com/mshots/v1/https://wpgeo.directory/<?php echo esc_attr($ac_site_args->slug); ?>?w=825&h=430"  alt="" class="w-100 p-0 m-0 mw-100 border-0 embed-responsive-item embed-item-cover-xy"  >
								<i class="far fa-eye"></i>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card-body d-none">
			<?php echo esc_attr( $ac_site_args->desc ); ?>
		</div>

		<div class="sd-src-theme d-none">
			<?php
			if(!empty($ac_site_args->theme)){
				?>
				<h4 class="h5"><?php _e("Theme","ayecode-connect");?></h4>
				<div><?php echo esc_attr($ac_site_args->theme->Name);?></div>
				<?php
			}
			?>
		</div>

		<div class="sd-src-plugins d-none">
			<?php
			if(!empty($ac_site_args->plugins)){
				?>
				<h4 class="h5"><?php _e("Plugins","ayecode-connect");?></h4>
				<ul>
				<?php
				foreach($ac_site_args->plugins as $slug => $plugin){
					?>
					<li>
						<?php echo esc_attr( $plugin->Name );

						if( !empty($plugin->{'Update ID'})){
							echo ' <span class="badge badge-danger">'.__("Paid","ayecode-connect").'</span>';
						}
						?>
					</li>
					<?php
				}
				?>
				</ul>
				<?php

			}
			?>
		</div>

		<div class="card-footer text-muted bg-white">
			<div class="row d-flex align-items-center">
				<div class="col">
					<div class="card-title h5 m-0 p-0">
						<?php echo esc_attr( $ac_site_args->title );?>
					</div>
				</div>
				<div class="col">
					<a href="https://wpgeo.directory/<?php echo esc_attr($ac_site_args->slug); ?>" onclick="ac_preview_site(this);return false;" class="btn btn-primary btn-sm ml-auto float-right" role="button" aria-pressed="true"><?php _e("View","ayecode-connect");?></a>
				</div>
			</div>

		</div>
	</div>
</div>