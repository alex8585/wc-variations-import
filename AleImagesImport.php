<?php
require_once('AleUtils.php');
class AleImagesImport
{

  private function upload_image_from_url($image_url, $parent_post_id = 0)
  {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $tmp = download_url(trim($image_url));


    if (is_wp_error($tmp)) {
      @unlink($tmp);
      return false;
    }

    $file_array = [
      'name' => basename($image_url),
      'tmp_name' => $tmp
    ];
    $id = media_handle_sideload($file_array, $parent_post_id);

    if (is_wp_error($id)) {
      @unlink($tmp);
      return false;
    }

    return $id;
  }

  function import($product_images)
  {
    if (!empty($product_images)) {
      $gallery_ids = [];
      $first_image_id = '';

      foreach ($product_images as $index => $image_url) {
        $image_id = $this->upload_image_from_url($image_url);
        if ($image_id) {
          if ($index === 0) {
            $first_image_id = $image_id;
          } else {
            $gallery_ids[] = $image_id;
          }
        }
      }
      return [
        'first_image_id' => $first_image_id,
        'gallery_ids' => $gallery_ids,
      ];
    }
  }
}
