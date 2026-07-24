<?php
/**
 * Community posts — a simple shared feed available to every logged-in
 * role (student, teacher, dean, admin). Any user can post an image with
 * an optional caption; any user can like a post and leave comments.
 * Posts/comments can only be deleted by their own author (or an admin).
 * Requires MIGRATION_ADD_posts.sql to have been run. Follows the same
 * self-healing pattern as includes/notes.php for hosts (like
 * InfinityFree) that block CREATE/ALTER from anything but phpMyAdmin.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schools.php'; // reuses inkwell_handle_logo_upload / inkwell_delete_upload

define('INKWELL_POSTS_SQL', "CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `caption` text DEFAULT NULL,
  `text_align` varchar(10) NOT NULL DEFAULT 'left',
  `text_bold` tinyint(1) NOT NULL DEFAULT 0,
  `bg_template` varchar(20) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video` varchar(255) DEFAULT NULL,
  `shared_post_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `shared_post_id` (`shared_post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_user` (`post_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_saves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_user` (`post_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_image_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `image_user` (`image_id`, `user_id`),
  KEY `image_id` (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_image_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `image_id` (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

/** Same 6-gradient palette used across the posts UI, keyed by user id. */
function inkwell_post_avatar_gradients() {
  return [
    'linear-gradient(135deg,#5B7CFA,#8B6CF9)',
    'linear-gradient(135deg,#33D19E,#1E9E69)',
    'linear-gradient(135deg,#F0A857,#E0245E)',
    'linear-gradient(135deg,#5B8DFF,#2F6FED)',
    'linear-gradient(135deg,#E0245E,#8B6CF9)',
    'linear-gradient(135deg,#1E9E69,#5B7CFA)',
  ];
}

function inkwell_avatar_gradient($grads, $userId) { return $grads[$userId % count($grads)]; }

/** Facebook-style "colorful text post" background templates, keyed by the id stored in posts.bg_template. Used both by the composer preview and the rendered card. */
function inkwell_post_bg_templates() {
  return [
    '1' => 'linear-gradient(135deg,#F97F51,#E0245E)', // Sunset
    '2' => 'linear-gradient(135deg,#36D1DC,#1E5799)',  // Ocean
    '3' => 'linear-gradient(135deg,#56AB2F,#0B8457)',  // Forest
    '4' => 'linear-gradient(135deg,#8E2DE2,#E0245E)',  // Berry
    '5' => 'linear-gradient(135deg,#232526,#0F2027)',  // Midnight
    '6' => 'linear-gradient(135deg,#F7971E,#FFD200)',  // Gold
  ];
}

/** "3m ago" / "2h ago" / "5d ago" style relative timestamp; falls back to a date past ~5 weeks. */
function inkwell_time_ago($datetime) {
  $then = strtotime((string) $datetime);
  if (!$then) return '';
  $diff = time() - $then;
  if ($diff < 5) return 'just now';
  if ($diff < 60) return $diff . 's ago';
  if ($diff < 3600) return floor($diff / 60) . 'm ago';
  if ($diff < 86400) return floor($diff / 3600) . 'h ago';
  if ($diff < 86400 * 7) return floor($diff / 86400) . 'd ago';
  if ($diff < 86400 * 35) return floor($diff / (86400 * 7)) . 'w ago';
  return date('M j, Y', $then);
}

function inkwell_posts_migration_hint() {
  return "Posts couldn't be saved because the posts tables don't exist on this database yet, and this host isn't letting the app create them automatically. "
       . "Fix: open phpMyAdmin for this database, go to the SQL tab, paste the contents of MIGRATION_ADD_posts.sql (or the box below), and click Go.";
}

/** True once we've confirmed the posts tables actually exist (cached per request). */
function inkwell_posts_tables_exist() {
  static $exists = null;
  if ($exists !== null) return $exists;
  try {
    $pdo = inkwell_db();
    $pdo->query('SELECT 1 FROM posts LIMIT 1');
    $pdo->query('SELECT 1 FROM post_likes LIMIT 1');
    $pdo->query('SELECT 1 FROM post_comments LIMIT 1');
    $exists = true;
  } catch (PDOException $e) {
    $exists = false;
  }
  return $exists;
}

/** Self-healing: creates the posts tables on first use if this host allows DDL over a normal DB connection; otherwise silently no-ops and callers fall back to the manual-setup message. */
function inkwell_ensure_posts_tables() {
  static $checked = false;
  if ($checked) return;
  $checked = true;
  if (!inkwell_posts_tables_exist()) {
    try {
      $pdo = inkwell_db();
      foreach (array_filter(array_map('trim', explode(';', INKWELL_POSTS_SQL))) as $stmt) {
        $pdo->exec($stmt);
      }
      $pdo->query('SELECT 1 FROM posts LIMIT 1');
    } catch (PDOException $e) {
      // Blocked or failed — inkwell_posts_tables_exist() will keep reporting false.
    }
  }
  // Older installs predate multi-photo posts and have no post_images
  // table. Add it the same self-healing way.
  if (!inkwell_posts_gallery_table_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec("CREATE TABLE `post_images` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `post_id` int(11) NOT NULL,
        `image` varchar(255) NOT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `post_id` (`post_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_gallery_table_ok() keeps checking.
    }
  }
  // Older installs had `image` as NOT NULL and no `video` column at all
  // (posts used to require an image). Relax/add those so text-only and
  // video posts work without anyone having to touch phpMyAdmin.
  if (!inkwell_posts_media_columns_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec('ALTER TABLE posts MODIFY COLUMN image varchar(255) DEFAULT NULL');
      $pdo->exec('ALTER TABLE posts ADD COLUMN video varchar(255) DEFAULT NULL AFTER image');
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_media_columns_ok() keeps checking.
    }
  }
  // Older installs predate the "Share" feature and have no shared_post_id
  // column. Add it the same self-healing way.
  if (!inkwell_posts_share_column_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec('ALTER TABLE posts ADD COLUMN shared_post_id int(11) DEFAULT NULL AFTER video');
      $pdo->exec('ALTER TABLE posts ADD KEY shared_post_id (shared_post_id)');
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_share_column_ok() keeps checking.
    }
  }
  // Older installs predate "share to my school page" and have no
  // shared_to_school_id column. Add it the same self-healing way.
  if (!inkwell_posts_school_share_column_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec('ALTER TABLE posts ADD COLUMN shared_to_school_id int(11) DEFAULT NULL AFTER shared_post_id');
      $pdo->exec('ALTER TABLE posts ADD KEY shared_to_school_id (shared_to_school_id)');
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_school_share_column_ok() keeps checking.
    }
  }
  // Older installs predate the "Save" (bookmark) feature and have no
  // post_saves table. Add it the same self-healing way.
  if (!inkwell_posts_saves_table_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec("CREATE TABLE `post_saves` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `post_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `post_user` (`post_id`, `user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_saves_table_ok() keeps checking.
    }
  }
  // Older installs predate the composer's formatting toolbar (bold,
  // alignment, colorful-background templates) and have no text_align /
  // text_bold / bg_template columns. Add them the same self-healing way.
  if (!inkwell_posts_style_columns_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec("ALTER TABLE posts ADD COLUMN text_align varchar(10) NOT NULL DEFAULT 'center' AFTER caption");
      $pdo->exec("ALTER TABLE posts ADD COLUMN text_bold tinyint(1) NOT NULL DEFAULT 0 AFTER text_align");
      $pdo->exec("ALTER TABLE posts ADD COLUMN bg_template varchar(20) DEFAULT NULL AFTER text_bold");
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_style_columns_ok() keeps checking.
    }
  }
  // Older installs predate per-photo likes/comments (Facebook-style: each
  // picture in a multi-photo post gets its own engagement) and have no
  // post_image_likes / post_image_comments tables. Add them the same
  // self-healing way.
  if (!inkwell_posts_image_engagement_tables_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec("CREATE TABLE `post_image_likes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `image_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `image_user` (`image_id`, `user_id`),
        KEY `image_id` (`image_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
      $pdo->exec("CREATE TABLE `post_image_comments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `image_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `comment` text NOT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `image_id` (`image_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_image_engagement_tables_ok() keeps checking.
    }
  }

  // Facebook-style post menu (Hide / Report) — hidden_posts and
  // post_reports tables. Add them the same self-healing way.
  if (!inkwell_posts_moderation_tables_ok()) {
    try {
      $pdo = inkwell_db();
      $pdo->exec("CREATE TABLE `hidden_posts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `post_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `post_user` (`post_id`, `user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
      $pdo->exec("CREATE TABLE `post_reports` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `post_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `post_id` (`post_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    } catch (PDOException $e) {
      // Blocked or already applied — inkwell_posts_moderation_tables_ok() keeps checking.
    }
  }
}

/** True once the `hidden_posts` and `post_reports` (Facebook-style post menu) tables exist (cached per request). */
function inkwell_posts_moderation_tables_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    $pdo = inkwell_db();
    $pdo->query('SELECT 1 FROM hidden_posts LIMIT 1');
    $pdo->query('SELECT 1 FROM post_reports LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** True once the `video` column exists (cached per request) — proxy for "media columns are up to date". */
function inkwell_posts_media_columns_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    inkwell_db()->query('SELECT video FROM posts LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** True once the `post_images` (multi-photo gallery) table exists (cached per request). */
function inkwell_posts_gallery_table_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    inkwell_db()->query('SELECT 1 FROM post_images LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** True once the `post_saves` (bookmark) table exists (cached per request). */
function inkwell_posts_saves_table_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    inkwell_db()->query('SELECT 1 FROM post_saves LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** True once the `post_image_likes` and `post_image_comments` (per-photo engagement) tables exist (cached per request). */
function inkwell_posts_image_engagement_tables_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    $pdo = inkwell_db();
    $pdo->query('SELECT 1 FROM post_image_likes LIMIT 1');
    $pdo->query('SELECT 1 FROM post_image_comments LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** True once the `text_align`/`text_bold`/`bg_template` composer-styling columns exist (cached per request). */
function inkwell_posts_style_columns_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    inkwell_db()->query('SELECT text_align, text_bold, bg_template FROM posts LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** True once the `shared_post_id` column exists (cached per request). */
function inkwell_posts_share_column_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    inkwell_db()->query('SELECT shared_post_id FROM posts LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** True once the `shared_to_school_id` column exists (cached per request). */
function inkwell_posts_school_share_column_ok() {
  static $ok = null;
  if ($ok !== null) return $ok;
  try {
    inkwell_db()->query('SELECT shared_to_school_id FROM posts LIMIT 1');
    $ok = true;
  } catch (PDOException $e) {
    $ok = false;
  }
  return $ok;
}

/** Validates and saves an uploaded video for a post. Mirrors inkwell_handle_logo_upload's shape/return contract. */
function inkwell_handle_post_video_upload($fileField) {
  $err = $_FILES[$fileField]['error'];
  if ($err !== UPLOAD_ERR_OK) {
    $messages = [
      UPLOAD_ERR_INI_SIZE => 'That video is too large for this server\'s upload limit.',
      UPLOAD_ERR_FORM_SIZE => 'That video is too large.',
      UPLOAD_ERR_PARTIAL => 'The upload was interrupted — please try again.',
      UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp folder for uploads — contact the site admin.',
      UPLOAD_ERR_CANT_WRITE => 'Could not write the file to disk — contact the site admin.',
    ];
    return ['ok' => false, 'error' => $messages[$err] ?? 'Upload failed (error code ' . $err . ').'];
  }

  $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/ogg' => 'ogv'];
  $tmpPath = $_FILES[$fileField]['tmp_name'];
  $mime = '';
  if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) { $mime = finfo_file($finfo, $tmpPath); finfo_close($finfo); }
  }

  if ($_FILES[$fileField]['size'] > 100 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'Video must be under 100MB.'];
  }
  if (!isset($allowed[$mime])) {
    return ['ok' => false, 'error' => 'Video must be an MP4, WEBM, MOV, or OGG file.'];
  }
  if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
  $filename = 'post_video_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
  if (!move_uploaded_file($tmpPath, INKWELL_UPLOADS_DIR . '/' . $filename)) {
    return ['ok' => false, 'error' => 'Could not save the uploaded video — check that assets/uploads/ is writable.'];
  }
  return ['ok' => true, 'filename' => $filename];
}

/**
 * Validates and saves one image item from a normalized multi-file upload
 * array (see inkwell_normalize_files()). Same validation rules as
 * inkwell_handle_logo_upload() (PNG/JPG/WEBP under 8MB) but works on a
 * single already-split item instead of a raw $_FILES field name, since
 * PHP's multi-file uploads arrive as parallel arrays, not one item.
 */
function inkwell_handle_post_image_item($item) {
  if (($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $messages = [
      UPLOAD_ERR_INI_SIZE => 'One of those photos is too large for this server\'s upload limit.',
      UPLOAD_ERR_FORM_SIZE => 'One of those photos is too large.',
      UPLOAD_ERR_PARTIAL => 'One of those uploads was interrupted — please try again.',
      UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp folder for uploads — contact the site admin.',
      UPLOAD_ERR_CANT_WRITE => 'Could not write a file to disk — contact the site admin.',
    ];
    return ['ok' => false, 'error' => $messages[$item['error']] ?? 'Upload failed (error code ' . $item['error'] . ').'];
  }

  $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
  $tmpPath = $item['tmp_name'];
  $info = @getimagesize($tmpPath);
  $mime = $info['mime'] ?? '';

  if ($item['size'] > 8 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'Each photo must be under 8MB.'];
  }
  if (!isset($allowed[$mime])) {
    return ['ok' => false, 'error' => 'Photos must be PNG, JPG, or WEBP files.'];
  }
  if (!is_dir(INKWELL_UPLOADS_DIR)) @mkdir(INKWELL_UPLOADS_DIR, 0775, true);
  $filename = 'post_img_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
  if (!move_uploaded_file($tmpPath, INKWELL_UPLOADS_DIR . '/' . $filename)) {
    return ['ok' => false, 'error' => 'Could not save an uploaded photo — check that assets/uploads/ is writable.'];
  }
  return ['ok' => true, 'filename' => $filename];
}

/** Turns PHP's parallel-array shape for a multi-file <input multiple> field into a normal list of [name, type, tmp_name, error, size] items. Safe to call on a field that wasn't submitted (returns []) or that only has one file (still returns a 1-item list). */
function inkwell_normalize_files($fileField) {
  if (empty($_FILES[$fileField]) || empty($_FILES[$fileField]['name'])) return [];
  $f = $_FILES[$fileField];
  if (!is_array($f['name'])) return [$f];
  $items = [];
  foreach ($f['name'] as $i => $name) {
    if ($name === '' || $name === null) continue;
    $items[] = [
      'name' => $name,
      'type' => $f['type'][$i] ?? '',
      'tmp_name' => $f['tmp_name'][$i] ?? '',
      'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
      'size' => $f['size'][$i] ?? 0,
    ];
  }
  return $items;
}

/**
 * Creates a post. Any combination of caption + a single optional media
 * file (image OR video, auto-detected) is allowed, but at least one of
 * the two must be present — no fully-empty posts. If $sharedPostId is
 * given, this is a "share" of another post: the caption becomes the
 * sharer's optional added thoughts, and no media/caption is required
 * since the post itself is not empty (the original is embedded).
 *
 * $mediaField can point at either a single-file input or a multi-file
 * <input multiple> — inkwell_normalize_files() handles both shapes. If
 * more than one image comes through, the first is stored in the legacy
 * `image` column (so old code paths that only look at posts.image keep
 * working) and every image is also stored in `post_images` for the
 * gallery renderer. Photos and a video can't be mixed in one post.
 *
 * $shareToSchoolId: if set (and the poster actually belongs to that
 * school), the post is tagged so it appears in that school's "Teacher &
 * Student posts" carousel on school.php / my-school.php. Left null, a
 * post only shows in the main Community feed — posting/sharing to a
 * school page is opt-in per post, not automatic just from being a
 * member of that school.
 *
 * $textAlign/$textBold/$bgTemplate come from the composer's formatting
 * toolbar (bold toggle, left/center/right alignment, and a Facebook-style
 * colorful-background template id from inkwell_post_bg_templates()). A
 * background template only ever renders on text-only posts (no photo/
 * video) — it's ignored at render time if media is also present, even if
 * a stale value made it into the row.
 */
function inkwell_create_post($userId, $caption, $mediaField = 'media', $sharedPostId = null, $shareToSchoolId = null, $textAlign = 'left', $textBold = false, $bgTemplate = null) {
  inkwell_ensure_posts_tables();
  $caption = trim((string) $caption);
  $items = inkwell_normalize_files($mediaField);
  $hasFile = !empty($items);

  $textAlign = in_array($textAlign, ['left', 'center', 'right'], true) ? $textAlign : 'left';
  $textBold = $textBold ? 1 : 0;
  $bgTemplates = inkwell_post_bg_templates();
  $bgTemplate = ($bgTemplate && isset($bgTemplates[$bgTemplate])) ? $bgTemplate : null;

  if (!$hasFile && $caption === '' && !$sharedPostId) {
    return ['ok' => false, 'error' => 'Write something, or add a photo or video, before posting.'];
  }
  if (count($items) > 10) {
    return ['ok' => false, 'error' => 'You can attach up to 10 photos per post.'];
  }

  $imageFilename = null;
  $videoFilename = null;
  $galleryFilenames = [];

  if ($hasFile) {
    // Detect video vs photo(s) from the first item's real mime type.
    $mime = '';
    if (($items[0]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo) { $mime = finfo_file($finfo, $items[0]['tmp_name']); finfo_close($finfo); }
    }

    if (strpos((string) $mime, 'video/') === 0) {
      if (count($items) > 1) return ['ok' => false, 'error' => 'You can attach a video or multiple photos, not both.'];
      $upload = inkwell_handle_post_video_upload($mediaField); // single-file field, unaffected by multi-upload changes
      if (!$upload['ok']) return ['ok' => false, 'error' => $upload['error']];
      $videoFilename = $upload['filename'];
    } else {
      foreach ($items as $item) {
        $upload = inkwell_handle_post_image_item($item);
        if (!$upload['ok']) {
          foreach ($galleryFilenames as $f) inkwell_delete_upload($f);
          return ['ok' => false, 'error' => $upload['error']];
        }
        $galleryFilenames[] = $upload['filename'];
      }
      $imageFilename = $galleryFilenames[0] ?? null;
    }
  }

  try {
    $pdo = inkwell_db();
    // created_at is written from PHP's own clock (not MySQL's DEFAULT
    // current_timestamp()) so the "time ago" shown right after posting is
    // always accurate, even if the DB server's clock/timezone disagrees
    // with PHP's — see the note in includes/db.php.
    $stmt = $pdo->prepare('INSERT INTO posts (user_id, caption, text_align, text_bold, bg_template, image, video, shared_post_id, shared_to_school_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $caption !== '' ? $caption : null, $textAlign, $textBold, $hasFile ? null : $bgTemplate, $imageFilename, $videoFilename, $sharedPostId ?: null, $shareToSchoolId ?: null, date('Y-m-d H:i:s')]);
    $postId = (int) $pdo->lastInsertId();

    if (count($galleryFilenames) > 1 && inkwell_posts_gallery_table_ok()) {
      $ins = $pdo->prepare('INSERT INTO post_images (post_id, image, sort_order) VALUES (?, ?, ?)');
      foreach ($galleryFilenames as $i => $fn) $ins->execute([$postId, $fn, $i]);
    }

    return ['ok' => true, 'id' => $postId];
  } catch (PDOException $e) {
    if ($imageFilename) inkwell_delete_upload($imageFilename);
    if ($videoFilename) inkwell_delete_upload($videoFilename);
    foreach ($galleryFilenames as $f) if ($f !== $imageFilename) inkwell_delete_upload($f);
    return ['ok' => false, 'error' => inkwell_posts_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** All gallery photos for one post, in order, as [{id, image}] rows — the id is what per-photo likes/comments key off of. Empty for single-image/video/text posts (those just use posts.image / posts.video directly). */
function inkwell_get_post_images($postId) {
  if (!inkwell_posts_gallery_table_ok()) return [];
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT id, image FROM post_images WHERE post_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
  } catch (PDOException $e) {
    return [];
  }
}

/** Batch-attaches each row's gallery images (as $row['images'] = [{id, image}, ...]) in one query instead of N+1 — call after any function that fetches a list of posts. Mutates $rows in place and also returns it. */
function inkwell_attach_gallery_images(&$rows) {
  foreach ($rows as &$row) {
    $row['images'] = [];
    $row['shared_images'] = [];
  }
  unset($row);
  if (!$rows || !inkwell_posts_gallery_table_ok()) return $rows;
  // Pull galleries for both the row's own post AND, when the row is a
  // share, the original (shared_post_id) post — a share needs its
  // embedded post's full gallery too, not just its first photo.
  $ids = array_map(function ($r) { return (int) $r['id']; }, $rows);
  foreach ($rows as $r) {
    if (!empty($r['shared_post_id'])) $ids[] = (int) $r['shared_post_id'];
  }
  $ids = array_values(array_unique($ids));
  if (!$ids) return $rows;
  try {
    $pdo = inkwell_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, post_id, image FROM post_images WHERE post_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
    $stmt->execute($ids);
    $byPost = [];
    foreach ($stmt->fetchAll() as $r) $byPost[(int) $r['post_id']][] = ['id' => (int) $r['id'], 'image' => $r['image']];
    foreach ($rows as &$row) {
      if (!empty($byPost[(int) $row['id']])) $row['images'] = $byPost[(int) $row['id']];
      if (!empty($row['shared_post_id']) && !empty($byPost[(int) $row['shared_post_id']])) {
        $row['shared_images'] = $byPost[(int) $row['shared_post_id']];
      }
    }
    unset($row);
  } catch (PDOException $e) {
    // Leave images empty — cards still render fine with just posts.image.
  }
  return $rows;
}

/** Facebook-style "Share": creates a new post that embeds/references an existing one, with optional added thoughts. Can't share a post that no longer exists, and sharing doesn't require any media of your own. */
function inkwell_create_share($userId, $originalPostId, $caption = '', $shareToSchoolId = null) {
  inkwell_ensure_posts_tables();
  $original = inkwell_get_post($originalPostId);
  if (!$original) return ['ok' => false, 'error' => 'This post is no longer available to share.'];
  return inkwell_create_post($userId, $caption, 'media', (int) $originalPostId, $shareToSchoolId);
}

/**
 * Feed of posts, newest first, with author name/avatar/role, like count,
 * comment count, and whether $viewerId has liked each one.
 */
function inkwell_list_posts($viewerId, $limit = 50) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT p.*, u.name AS author_name, u.role AS author_role, u.avatar AS author_avatar,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
              (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comment_count,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id) AS save_count,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id AND user_id = ?) AS saved_by_me,
              sp.caption AS shared_caption, sp.image AS shared_image, sp.video AS shared_video, sp.created_at AS shared_created_at,
              su.id AS shared_author_id, su.name AS shared_author_name, su.role AS shared_author_role, su.avatar AS shared_author_avatar
       FROM posts p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN posts sp ON sp.id = p.shared_post_id
       LEFT JOIN users su ON su.id = sp.user_id
       " . (inkwell_posts_moderation_tables_ok() ? "LEFT JOIN hidden_posts hp ON hp.post_id = p.id AND hp.user_id = ? WHERE hp.id IS NULL" : "") . "
       ORDER BY p.created_at DESC, p.id DESC
       LIMIT " . (int) $limit
    );
    $params = [$viewerId, $viewerId];
    if (inkwell_posts_moderation_tables_ok()) $params[] = $viewerId;
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return inkwell_attach_gallery_images($rows);
  } catch (PDOException $e) {
    return [];
  }
}

/** Only this user's own posts — powers the "Posts" tab on their profile. */
/** All posts by one user, newest first, same row shape as inkwell_list_posts() — powers both the profile popup preview and the full profile.php Timeline (which pages through with $offset). */
function inkwell_list_posts_by_user($userId, $viewerId, $limit = 50, $offset = 0) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT p.*, u.name AS author_name, u.role AS author_role, u.avatar AS author_avatar,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
              (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comment_count,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id) AS save_count,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id AND user_id = ?) AS saved_by_me,
              sp.caption AS shared_caption, sp.image AS shared_image, sp.video AS shared_video, sp.created_at AS shared_created_at,
              su.id AS shared_author_id, su.name AS shared_author_name, su.role AS shared_author_role, su.avatar AS shared_author_avatar
       FROM posts p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN posts sp ON sp.id = p.shared_post_id
       LEFT JOIN users su ON su.id = sp.user_id
       WHERE p.user_id = ?
       ORDER BY p.created_at DESC, p.id DESC
       LIMIT " . (int) $limit . " OFFSET " . (int) $offset
    );
    $stmt->execute([$viewerId, $viewerId, $userId]);
    $rows = $stmt->fetchAll();
    return inkwell_attach_gallery_images($rows);
  } catch (PDOException $e) {
    return [];
  }
}

/**
 * Bundle for the clickable-author profile popup on the community feed:
 * basic user info, their post count, and their recent posts rendered as
 * full post cards (same look as the main feed) — the Facebook-style
 * "click a name, see their profile and posts" popup.
 */
function inkwell_get_post_author_profile($userId, $viewerId, $isAdmin, $limit = 8) {
  $pdo = inkwell_db();
  $stmt = $pdo->prepare('SELECT id, name, email, role, avatar, course, created_at FROM users WHERE id = ?');
  $stmt->execute([$userId]);
  $author = $stmt->fetch();
  if (!$author) return null;

  return [
    'author' => $author,
    'post_count' => inkwell_count_user_posts($userId),
    'posts' => inkwell_list_posts_by_user($userId, $viewerId, $limit),
  ];
}

/** Cheap count of a user's own posts, for the profile stat pill. */
function inkwell_count_user_posts($userId) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
  } catch (PDOException $e) {
    return 0;
  }
}

/** Posts newer than $afterId, oldest-first — feeds the community page's realtime poll. */
function inkwell_list_new_posts($afterId, $viewerId, $limit = 20) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT p.*, u.name AS author_name, u.role AS author_role, u.avatar AS author_avatar,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
              (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comment_count,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id) AS save_count,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id AND user_id = ?) AS saved_by_me,
              sp.caption AS shared_caption, sp.image AS shared_image, sp.video AS shared_video, sp.created_at AS shared_created_at,
              su.id AS shared_author_id, su.name AS shared_author_name, su.role AS shared_author_role, su.avatar AS shared_author_avatar
       FROM posts p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN posts sp ON sp.id = p.shared_post_id
       LEFT JOIN users su ON su.id = sp.user_id
       " . (inkwell_posts_moderation_tables_ok() ? "LEFT JOIN hidden_posts hp ON hp.post_id = p.id AND hp.user_id = ?" : "") . "
       WHERE p.id > ?" . (inkwell_posts_moderation_tables_ok() ? " AND hp.id IS NULL" : "") . "
       ORDER BY p.id ASC
       LIMIT " . (int) $limit
    );
    $params = [$viewerId, $viewerId];
    if (inkwell_posts_moderation_tables_ok()) $params[] = $viewerId;
    $params[] = (int) $afterId;
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return inkwell_attach_gallery_images($rows);
  } catch (PDOException $e) {
    return [];
  }
}

/**
 * Posts explicitly shared to one school — powers the "Teacher & Student
 * posts" swipe carousel on the school page and My School. Unlike a
 * generic "everything this school's people posted" feed, a post only
 * shows here if its author opted in via the "Also post to my school
 * page" checkbox when composing/sharing (posts.shared_to_school_id).
 * Same row shape as inkwell_list_posts() (author info, like/comment
 * counts, gallery images).
 */
function inkwell_list_school_posts($schoolId, $viewerId, $limit = 20) {
  inkwell_ensure_posts_tables();
  if (!inkwell_posts_school_share_column_ok()) return [];
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT p.*, u.name AS author_name, u.role AS author_role, u.avatar AS author_avatar,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
              (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comment_count,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id) AS save_count,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id AND user_id = ?) AS saved_by_me,
              sp.caption AS shared_caption, sp.image AS shared_image, sp.video AS shared_video, sp.created_at AS shared_created_at,
              su.id AS shared_author_id, su.name AS shared_author_name, su.role AS shared_author_role, su.avatar AS shared_author_avatar
       FROM posts p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN posts sp ON sp.id = p.shared_post_id
       LEFT JOIN users su ON su.id = sp.user_id
       WHERE p.shared_to_school_id = ?
       ORDER BY p.created_at DESC, p.id DESC
       LIMIT " . (int) $limit
    );
    $stmt->execute([$viewerId, $viewerId, (int) $schoolId]);
    $rows = $stmt->fetchAll();
    return inkwell_attach_gallery_images($rows);
  } catch (PDOException $e) {
    return [];
  }
}

/**
 * Renders the horizontally-swipeable "Teacher & Student posts" carousel
 * for a school page. Each card is a compact read-only teaser (photo/
 * video, caption, author, like/comment counts) — clicking the photo
 * still opens the full lightbox (global script), and "View & share"
 * takes the visitor to the full interactive post on the Community feed,
 * where the Share modal opens automatically (see posts.php's ?share=
 * handling), rather than re-implementing likes/comments/sharing twice.
 */
function inkwell_render_school_posts_swipe($posts) {
  if (empty($posts)) {
    return '<p class="admin-sub">No posts from teachers or students here yet.</p>';
  }
  $grads = inkwell_post_avatar_gradients();
  ob_start();
  ?>
  <div class="school-swipe-row">
    <?php foreach ($posts as $p): ?>
      <?php $grad = inkwell_avatar_gradient($grads, (int) $p['user_id']); ?>
      <div class="school-swipe-card post-card admin-card glass-card">
        <div class="post-head">
          <span class="post-avatar" style="background:<?php echo $grad; ?>;">
            <?php if (!empty($p['author_avatar'])): ?>
              <img src="/assets/uploads/<?php echo htmlspecialchars($p['author_avatar']); ?>" alt="" loading="lazy">
            <?php else: ?>
              <?php echo strtoupper(substr($p['author_name'], 0, 1)); ?>
            <?php endif; ?>
          </span>
          <div class="post-headtext">
            <div class="post-author"><?php echo htmlspecialchars($p['author_name']); ?></div>
            <div class="post-meta">
              <span class="post-role-chip role-<?php echo htmlspecialchars($p['author_role']); ?>"><?php echo htmlspecialchars($p['author_role']); ?></span>
              <span>·</span>
              <span><?php echo htmlspecialchars(inkwell_time_ago($p['created_at'])); ?></span>
            </div>
          </div>
        </div>

        <?php if (!empty($p['video'])): ?>
          <div class="post-image-wrap"><video class="post-video" controls preload="metadata" src="/assets/uploads/<?php echo htmlspecialchars($p['video']); ?>"></video></div>
        <?php elseif (!empty($p['images'])): ?>
          <?php echo inkwell_render_post_gallery($p['images'], 'school-post-' . (int) $p['id'], null, false); ?>
        <?php elseif (!empty($p['image'])): ?>
          <?php echo inkwell_render_post_gallery([$p['image']], 'school-post-' . (int) $p['id'], null, false); ?>
        <?php endif; ?>

        <?php if (!empty($p['caption'])): ?>
          <p class="post-caption"><?php echo htmlspecialchars(mb_strimwidth($p['caption'], 0, 140, '…')); ?></p>
        <?php endif; ?>

        <div class="post-stats" style="<?php echo ((int) $p['like_count'] > 0 || (int) $p['comment_count'] > 0) ? '' : 'display:none;'; ?>">
          <span style="<?php echo (int) $p['like_count'] > 0 ? '' : 'display:none;'; ?>">♥ <?php echo (int) $p['like_count']; ?></span>
          <span style="<?php echo (int) $p['comment_count'] > 0 ? '' : 'display:none;'; ?>"><?php echo (int) $p['comment_count']; ?> comment<?php echo (int) $p['comment_count'] === 1 ? '' : 's'; ?></span>
        </div>

        <div class="post-actions">
          <a class="post-action-btn" href="/posts.php#post-<?php echo (int) $p['id']; ?>">💬 View</a>
          <a class="post-action-btn" href="/posts.php?share=<?php echo (int) $p['id']; ?>#post-<?php echo (int) $p['id']; ?>">🔁 Share</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
}

/** Fresh like/comment counts for a batch of post ids — lets the realtime poll sync counters set by other people without touching whatever the visitor is currently doing in the feed. */
function inkwell_get_post_counts($ids, $viewerId) {
  $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
  if (!$ids) return [];
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
      "SELECT p.id,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
              (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comment_count,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me
       FROM posts p WHERE p.id IN ($placeholders)"
    );
    $stmt->execute(array_merge([$viewerId], $ids));
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
      $out[(int) $row['id']] = [
        'like_count' => (int) $row['like_count'],
        'comment_count' => (int) $row['comment_count'],
        'liked_by_me' => (bool) $row['liked_by_me'],
      ];
    }
    return $out;
  } catch (PDOException $e) {
    return [];
  }
}

function inkwell_get_post($id) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
  } catch (PDOException $e) {
    return null;
  }
}

/** One post, fully hydrated with author name/avatar/role, like/comment counts, viewer's like state, and shared-post embed data — everything inkwell_render_post_card() needs. Used right after creating a post or a share so the returned card renders correctly. */
function inkwell_get_post_full($id, $viewerId) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT p.*, u.name AS author_name, u.role AS author_role, u.avatar AS author_avatar,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS like_count,
              (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) AS comment_count,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id) AS save_count,
              (SELECT COUNT(*) FROM post_saves WHERE post_id = p.id AND user_id = ?) AS saved_by_me,
              sp.caption AS shared_caption, sp.image AS shared_image, sp.video AS shared_video, sp.created_at AS shared_created_at,
              su.id AS shared_author_id, su.name AS shared_author_name, su.role AS shared_author_role, su.avatar AS shared_author_avatar
       FROM posts p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN posts sp ON sp.id = p.shared_post_id
       LEFT JOIN users su ON su.id = sp.user_id
       WHERE p.id = ?"
    );
    $stmt->execute([$viewerId, $viewerId, $id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['images'] = inkwell_get_post_images($id);
    $row['shared_images'] = !empty($row['shared_post_id']) ? inkwell_get_post_images($row['shared_post_id']) : [];
    return $row;
  } catch (PDOException $e) {
    return null;
  }
}

/** Toggles a like on/off for this user. Returns the new liked state and count. */
function inkwell_toggle_post_like($postId, $userId) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?');
    $stmt->execute([$postId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
      $pdo->prepare('DELETE FROM post_likes WHERE id = ?')->execute([$existing['id']]);
      $liked = false;
    } else {
      $pdo->prepare('INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)')->execute([$postId, $userId]);
      $liked = true;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
    $stmt->execute([$postId]);
    $count = (int) $stmt->fetchColumn();
    return ['ok' => true, 'liked' => $liked, 'count' => $count];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_posts_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** Toggles a save/bookmark on/off for this user. Returns the new saved state and count, same shape as inkwell_toggle_post_like(). */
function inkwell_toggle_post_save($postId, $userId) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT id FROM post_saves WHERE post_id = ? AND user_id = ?');
    $stmt->execute([$postId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
      $pdo->prepare('DELETE FROM post_saves WHERE id = ?')->execute([$existing['id']]);
      $saved = false;
    } else {
      $pdo->prepare('INSERT INTO post_saves (post_id, user_id) VALUES (?, ?)')->execute([$postId, $userId]);
      $saved = true;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM post_saves WHERE post_id = ?');
    $stmt->execute([$postId]);
    $count = (int) $stmt->fetchColumn();
    return ['ok' => true, 'saved' => $saved, 'count' => $count];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_posts_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** Absolute, shareable URL for one post (used by the copy-link box on the post card). */
function inkwell_post_url($postId) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host . '/posts.php#post-' . (int) $postId;
}

function inkwell_list_comments($postId) {
  inkwell_ensure_posts_tables();
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT c.*, u.name AS author_name, u.avatar AS author_avatar
       FROM post_comments c JOIN users u ON u.id = c.user_id
       WHERE c.post_id = ? ORDER BY c.created_at ASC, c.id ASC"
    );
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
  } catch (PDOException $e) {
    return [];
  }
}

function inkwell_add_comment($postId, $userId, $text) {
  inkwell_ensure_posts_tables();
  $text = trim((string) $text);
  if ($text === '') return ['ok' => false, 'error' => 'Write something before posting a comment.'];
  if (!inkwell_get_post($postId)) return ['ok' => false, 'error' => 'Post not found.'];

  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('INSERT INTO post_comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$postId, $userId, $text, date('Y-m-d H:i:s')]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_posts_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** Deletes a post — only its author or an admin may. */
function inkwell_delete_post($postId, $userId, $isAdmin = false) {
  $post = inkwell_get_post($postId);
  if (!$post) return ['ok' => false, 'error' => 'Post not found.'];
  if (!$isAdmin && (int) $post['user_id'] !== (int) $userId) {
    return ['ok' => false, 'error' => 'You can only delete your own posts.'];
  }
  try {
    $pdo = inkwell_db();
    $galleryImages = inkwell_get_post_images($postId);
    $pdo->prepare('DELETE FROM post_likes WHERE post_id = ?')->execute([$postId]);
    $pdo->prepare('DELETE FROM post_comments WHERE post_id = ?')->execute([$postId]);
    if ($galleryImages && inkwell_posts_image_engagement_tables_ok()) {
      $imageIds = array_map(function ($img) { return (int) $img['id']; }, $galleryImages);
      $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
      $pdo->prepare("DELETE FROM post_image_likes WHERE image_id IN ($placeholders)")->execute($imageIds);
      $pdo->prepare("DELETE FROM post_image_comments WHERE image_id IN ($placeholders)")->execute($imageIds);
    }
    if (inkwell_posts_gallery_table_ok()) $pdo->prepare('DELETE FROM post_images WHERE post_id = ?')->execute([$postId]);
    $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);
    if ($galleryImages) {
      foreach ($galleryImages as $img) inkwell_delete_upload($img['image']);
    } else {
      inkwell_delete_upload($post['image'] ?? null);
    }
    inkwell_delete_upload($post['video'] ?? null);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => 'Could not delete post (' . $e->getMessage() . ').'];
  }
}

/** Hides a post from just this viewer's own feed (Facebook-style "Hide post") — doesn't touch anyone else's view of it. */
function inkwell_hide_post($postId, $userId) {
  inkwell_ensure_posts_tables();
  if (!inkwell_posts_moderation_tables_ok()) {
    return ['ok' => false, 'error' => "Couldn't hide this post right now — try again in a moment."];
  }
  $post = inkwell_get_post($postId);
  if (!$post) return ['ok' => false, 'error' => 'Post not found.'];
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('INSERT IGNORE INTO hidden_posts (post_id, user_id) VALUES (?, ?)');
    $stmt->execute([$postId, $userId]);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => 'Could not hide post.'];
  }
}

/** Flags a post for admin review (Facebook-style "Report post"). One report per user per post; re-reporting just updates the reason. */
function inkwell_report_post($postId, $userId, $reason = '') {
  inkwell_ensure_posts_tables();
  if (!inkwell_posts_moderation_tables_ok()) {
    return ['ok' => false, 'error' => "Couldn't submit this report right now — try again in a moment."];
  }
  $post = inkwell_get_post($postId);
  if (!$post) return ['ok' => false, 'error' => 'Post not found.'];
  $reason = trim((string) $reason);
  if (strlen($reason) > 255) $reason = substr($reason, 0, 255);
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('INSERT INTO post_reports (post_id, user_id, reason) VALUES (?, ?, ?)');
    $stmt->execute([$postId, $userId, $reason !== '' ? $reason : null]);
    // Also hide it from the reporter's own feed, same as Facebook does.
    inkwell_hide_post($postId, $userId);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => 'Could not submit report.'];
  }
}

/** Edits a post's caption/formatting — only its author (or an admin) may. Media/attachments are left untouched. */
function inkwell_edit_post($postId, $userId, $caption, $textAlign = 'left', $textBold = false, $isAdmin = false, $bgTemplate = null) {
  $post = inkwell_get_post($postId);
  if (!$post) return ['ok' => false, 'error' => 'Post not found.'];
  if (!$isAdmin && (int) $post['user_id'] !== (int) $userId) {
    return ['ok' => false, 'error' => 'You can only edit your own posts.'];
  }
  $caption = trim((string) $caption);
  if (strlen($caption) > 5000) $caption = substr($caption, 0, 5000);
  $textAlign = in_array($textAlign, ['left', 'center', 'right'], true) ? $textAlign : 'left';
  // Background templates only make sense on text-only posts (no photo/video),
  // same rule inkwell_create_post() applies.
  $isTextOnly = empty($post['video']) && empty($post['image']);
  $bgTemplates = inkwell_post_bg_templates();
  $bgTemplate = ($isTextOnly && $bgTemplate && isset($bgTemplates[$bgTemplate])) ? $bgTemplate : null;
  try {
    $pdo = inkwell_db();
    if (inkwell_posts_style_columns_ok()) {
      $stmt = $pdo->prepare('UPDATE posts SET caption = ?, text_align = ?, text_bold = ?, bg_template = ? WHERE id = ?');
      $stmt->execute([$caption !== '' ? $caption : null, $textAlign, $textBold ? 1 : 0, $bgTemplate, $postId]);
    } else {
      $stmt = $pdo->prepare('UPDATE posts SET caption = ? WHERE id = ?');
      $stmt->execute([$caption !== '' ? $caption : null, $postId]);
    }
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => 'Could not update post.'];
  }
}

/** Renders one comment's markup — identical output whether used in the initial page render or an AJAX fragment. */
function inkwell_render_comment($c, $post, $user, $isAdmin) {
  $grads = inkwell_post_avatar_gradients();
  $cGrad = inkwell_avatar_gradient($grads, (int) $c['user_id']);
  ob_start();
  ?>
  <div class="post-comment" id="comment-<?php echo (int) $c['id']; ?>">
    <span class="post-comment-avatar" style="background:<?php echo $cGrad; ?>;">
      <?php if (!empty($c['author_avatar'])): ?>
        <img src="/assets/uploads/<?php echo htmlspecialchars($c['author_avatar']); ?>" alt="" loading="lazy">
      <?php else: ?>
        <?php echo strtoupper(substr($c['author_name'], 0, 1)); ?>
      <?php endif; ?>
    </span>
    <div class="post-comment-col">
      <span class="post-comment-author"><?php echo htmlspecialchars($c['author_name']); ?></span>
      <div class="post-comment-body"><?php echo htmlspecialchars($c['comment']); ?></div>
      <div class="post-comment-foot">
        <span class="post-comment-time"><?php echo htmlspecialchars(inkwell_time_ago($c['created_at'])); ?></span>
        <?php if ($isAdmin || (int) $c['user_id'] === (int) $user['id']): ?>
          <span>·</span>
          <button type="button" class="post-comment-del" data-comment-delete data-comment-id="<?php echo (int) $c['id']; ?>" data-post-id="<?php echo (int) $post['id']; ?>">Delete</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

/**
 * Renders a Facebook-style tiled photo grid for a multi-photo post: 1
 * photo full-width, 2 side-by-side, 3 as one big + two stacked, 4+ as a
 * 2x2 grid with a "+N" overlay on the last visible tile. Every tile
 * carries data-lightbox-src/data-lightbox-group so the global lightbox
 * script (assets/js/post-lightbox.js) can open a fullscreen, arrow-key/
 * swipe-navigable viewer through the whole set on click — this same
 * markup is reused verbatim by the "Share" preview clone, so the
 * lightbox keeps working on shared/embedded posts too.
 *
 * Facebook-style per-photo engagement: when $interactive is true and an
 * image came from the post_images gallery table (so it has a real id),
 * each photo gets its own like/comment counts baked into the JSON stash
 * so the lightbox can show a like button + comment thread scoped to
 * that exact picture — not the post as a whole. $viewerId is needed to
 * know whether *this* viewer has liked each photo. Pass $interactive =
 * false for read-only contexts (e.g. the school-page swipe teaser)
 * where photos should still be viewable full-screen but with no
 * per-photo like/comment panel.
 *
 * $images accepts either plain filename strings (legacy single-image
 * posts, which have no post_images row/id) or ['id' => ..., 'image' =>
 * ...] rows from inkwell_get_post_images() / inkwell_attach_gallery_images().
 */
function inkwell_render_post_gallery($images, $groupId, $viewerId = null, $interactive = true) {
  $items = array_map(function ($img) {
    return is_array($img) ? ['id' => (int) $img['id'], 'image' => $img['image']] : ['id' => null, 'image' => $img];
  }, $images);
  $count = count($items);
  if ($count === 0) return '';

  $imageIds = array_values(array_filter(array_map(function ($it) { return $it['id']; }, $items)));
  $engagement = ($interactive && $imageIds) ? inkwell_get_image_counts($imageIds, $viewerId) : [];

  $buildEntry = function ($it) use ($engagement, $interactive) {
    $entry = ['src' => '/assets/uploads/' . $it['image'], 'id' => $it['id']];
    if ($interactive && $it['id'] && isset($engagement[$it['id']])) {
      $entry['likeCount'] = $engagement[$it['id']]['like_count'];
      $entry['liked'] = $engagement[$it['id']]['liked_by_me'];
      $entry['commentCount'] = $engagement[$it['id']]['comment_count'];
    }
    return $entry;
  };

  if ($count === 1) {
    ob_start(); ?>
    <div class="post-image-wrap"<?php echo $interactive ? '' : ' data-lightbox-readonly="1"'; ?> data-lightbox-full='<?php echo htmlspecialchars(json_encode([$buildEntry($items[0])]), ENT_QUOTES); ?>'>
      <img class="post-image" data-lightbox-src="<?php echo htmlspecialchars('/assets/uploads/' . $items[0]['image']); ?>" data-lightbox-group="<?php echo htmlspecialchars($groupId); ?>" data-lightbox-index="0" src="<?php echo htmlspecialchars('/assets/uploads/' . $items[0]['image']); ?>" alt="" loading="lazy">
    </div>
    <?php return ob_get_clean();
  }
  $visible = array_slice($items, 0, 4);
  $extra = $count - 4;
  ob_start(); ?>
  <div class="post-image-wrap post-gallery post-gallery-<?php echo min($count, 4); ?>"<?php echo $interactive ? '' : ' data-lightbox-readonly="1"'; ?>>
    <?php foreach ($visible as $i => $it): ?>
      <div class="post-gallery-tile<?php echo ($i === 3 && $extra > 0) ? ' post-gallery-more' : ''; ?>" <?php echo ($i === 3 && $extra > 0) ? 'data-more-count="+' . (int) $extra . '"' : ''; ?>>
        <img data-lightbox-src="<?php echo htmlspecialchars('/assets/uploads/' . $it['image']); ?>" data-lightbox-group="<?php echo htmlspecialchars($groupId); ?>" data-lightbox-index="<?php echo (int) $i; ?>" src="<?php echo htmlspecialchars('/assets/uploads/' . $it['image']); ?>" alt="" loading="lazy">
      </div>
    <?php endforeach; ?>
  </div>
  <?php
  // The lightbox needs every photo (not just the 4 visible tiles), so
  // stash the full list — including each photo's own like/comment
  // engagement — as JSON on the wrapper itself.
  $html = ob_get_clean();
  $fullList = htmlspecialchars(json_encode(array_map($buildEntry, $items)), ENT_QUOTES);
  return preg_replace('/class="post-image-wrap post-gallery/', 'data-lightbox-full=\'' . $fullList . '\' class="post-image-wrap post-gallery', $html, 1);
}

/** Renders one full post card's markup — identical output whether used in the initial page render or an AJAX fragment. */
function inkwell_render_post_card($post, $user, $isAdmin) {
  $grads = inkwell_post_avatar_gradients();
  $comments = inkwell_list_comments($post['id']);
  $canDeletePost = $isAdmin || (int) $post['user_id'] === (int) $user['id'];
  $authorGrad = inkwell_avatar_gradient($grads, (int) $post['user_id']);
  $isShare = !empty($post['shared_post_id']);
  $sharedGone = $isShare && empty($post['shared_author_id']);
  ob_start();
  ?>
  <article class="admin-card glass-card post-card" id="post-<?php echo (int) $post['id']; ?>">
    <div class="post-head">
      <span class="post-avatar post-author-link" style="background:<?php echo $authorGrad; ?>;" data-modal-open="postAuthorProfileModal" data-post-user-id="<?php echo (int) $post['user_id']; ?>" role="button" tabindex="0" title="View <?php echo htmlspecialchars($post['author_name']); ?>'s profile">
        <?php if (!empty($post['author_avatar'])): ?>
          <img src="/assets/uploads/<?php echo htmlspecialchars($post['author_avatar']); ?>" alt="" loading="lazy">
        <?php else: ?>
          <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
        <?php endif; ?>
      </span>
      <div class="post-headtext">
        <div class="post-author post-author-link" data-modal-open="postAuthorProfileModal" data-post-user-id="<?php echo (int) $post['user_id']; ?>" role="button" tabindex="0"><?php echo htmlspecialchars($post['author_name']); ?></div>
        <div class="post-meta">
          <span class="post-role-chip role-<?php echo htmlspecialchars($post['author_role']); ?>"><?php echo htmlspecialchars($post['author_role']); ?></span>
          <span>·</span>
          <span title="<?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($post['created_at']))); ?>"><?php echo htmlspecialchars(inkwell_time_ago($post['created_at'])); ?></span>
          <?php if ($isShare): ?>
            <span>·</span>
            <span class="post-shared-tag">🔁 shared a post</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="post-menu-wrap" data-post-menu>
        <button type="button" class="post-kebab-btn" data-post-menu-toggle data-post-id="<?php echo (int) $post['id']; ?>" title="Post options" aria-label="Post options" aria-haspopup="true" aria-expanded="false">⋯</button>
        <div class="post-menu-dropdown" data-post-menu-dropdown hidden>
          <?php if ($canDeletePost): ?>
            <a class="post-menu-item" href="/edit-post.php?id=<?php echo (int) $post['id']; ?>&amp;return=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/posts.php'); ?>">
              <span class="post-menu-icon">✏️</span> Edit post
            </a>
            <button type="button" class="post-menu-item danger" data-post-delete data-post-id="<?php echo (int) $post['id']; ?>">
              <span class="post-menu-icon">🗑️</span> Delete post
            </button>
          <?php else: ?>
            <button type="button" class="post-menu-item" data-post-hide data-post-id="<?php echo (int) $post['id']; ?>">
              <span class="post-menu-icon">🙈</span> Hide post
            </button>
            <a class="post-menu-item danger" href="/report-post.php?id=<?php echo (int) $post['id']; ?>&amp;return=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/posts.php'); ?>">
              <span class="post-menu-icon">🚩</span> Report post
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($post['video'])): ?>
      <div class="post-image-wrap">
        <video class="post-video" controls preload="metadata" src="/assets/uploads/<?php echo htmlspecialchars($post['video']); ?>"></video>
      </div>
    <?php elseif (!empty($post['images'])): ?>
      <?php echo inkwell_render_post_gallery($post['images'], 'post-' . (int) $post['id'], $user['id']); ?>
    <?php elseif (!empty($post['image'])): ?>
      <?php echo inkwell_render_post_gallery([$post['image']], 'post-' . (int) $post['id'], $user['id']); ?>
    <?php endif; ?>

    <?php
      $isTextOnly = empty($post['video']) && empty($post['image']);
      $capAlign = in_array($post['text_align'] ?? 'left', ['left', 'center', 'right'], true) ? $post['text_align'] : 'left';
      $capBold = !empty($post['text_bold']);
      $bgTemplates = inkwell_post_bg_templates();
      $capBgId = $isTextOnly && !empty($post['bg_template']) && isset($bgTemplates[$post['bg_template']]) ? $post['bg_template'] : null;
      $capJustify = $capAlign === 'center' ? 'center' : ($capAlign === 'right' ? 'flex-end' : 'flex-start');
    ?>
    <?php if (!empty($post['caption'])): ?>
      <?php if ($capBgId): ?>
        <p class="post-caption text-only post-caption-bg post-tpl-<?php echo htmlspecialchars($capBgId); ?>" data-post-caption data-post-id="<?php echo (int) $post['id']; ?>" style="background:<?php echo $bgTemplates[$capBgId]; ?>; text-align:<?php echo $capAlign; ?>; justify-content:<?php echo $capJustify; ?>;"><?php echo htmlspecialchars($post['caption']); ?></p>
      <?php elseif ($isTextOnly): ?>
        <p class="post-caption text-only" data-post-caption data-post-id="<?php echo (int) $post['id']; ?>" style="text-align:<?php echo $capAlign; ?>;<?php echo $capBold ? ' font-weight:800;' : ''; ?>"><?php echo htmlspecialchars($post['caption']); ?></p>
      <?php else: ?>
        <p class="post-caption" data-post-caption data-post-id="<?php echo (int) $post['id']; ?>"><?php echo htmlspecialchars($post['caption']); ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($isShare): ?>
      <div class="post-shared-embed<?php echo (empty($post['video']) && empty($post['image']) && empty($post['caption'])) ? ' no-top-margin' : ''; ?>">
        <?php if ($sharedGone): ?>
          <p class="post-shared-gone">This post is no longer available.</p>
        <?php else: ?>
          <?php $sGrad = inkwell_avatar_gradient($grads, (int) $post['shared_author_id']); ?>
          <div class="post-shared-head">
            <span class="post-avatar post-author-link" style="width:32px;height:32px;font-size:0.8rem;background:<?php echo $sGrad; ?>;" data-modal-open="postAuthorProfileModal" data-post-user-id="<?php echo (int) $post['shared_author_id']; ?>" role="button" tabindex="0" title="View <?php echo htmlspecialchars($post['shared_author_name']); ?>'s profile">
              <?php if (!empty($post['shared_author_avatar'])): ?>
                <img src="/assets/uploads/<?php echo htmlspecialchars($post['shared_author_avatar']); ?>" alt="" loading="lazy">
              <?php else: ?>
                <?php echo strtoupper(substr($post['shared_author_name'], 0, 1)); ?>
              <?php endif; ?>
            </span>
            <div class="post-headtext">
              <div class="post-author post-author-link" style="font-size:0.85rem;" data-modal-open="postAuthorProfileModal" data-post-user-id="<?php echo (int) $post['shared_author_id']; ?>" role="button" tabindex="0"><?php echo htmlspecialchars($post['shared_author_name']); ?></div>
              <div class="post-meta">
                <span title="<?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($post['shared_created_at']))); ?>"><?php echo htmlspecialchars(inkwell_time_ago($post['shared_created_at'])); ?></span>
              </div>
            </div>
          </div>
          <?php if (!empty($post['shared_caption'])): ?>
            <p class="post-shared-caption"><?php echo htmlspecialchars($post['shared_caption']); ?></p>
          <?php endif; ?>
          <?php if (!empty($post['shared_video'])): ?>
            <div class="post-image-wrap">
              <video class="post-video" controls preload="metadata" src="/assets/uploads/<?php echo htmlspecialchars($post['shared_video']); ?>"></video>
            </div>
          <?php elseif (!empty($post['shared_images'])): ?>
            <?php echo inkwell_render_post_gallery($post['shared_images'], 'shared-' . (int) $post['shared_post_id'], $user['id']); ?>
          <?php elseif (!empty($post['shared_image'])): ?>
            <?php echo inkwell_render_post_gallery([$post['shared_image']], 'shared-' . (int) $post['shared_post_id'], $user['id']); ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php $postUrl = inkwell_post_url($post['id']); ?>
    <div class="post-action-bar" id="post-stats-<?php echo (int) $post['id']; ?>">
      <div class="post-action-bar-left">
        <button type="button" class="post-icon-btn post-like-btn<?php echo $post['liked_by_me'] ? ' liked' : ''; ?>" data-like-btn data-post-id="<?php echo (int) $post['id']; ?>" title="<?php echo $post['liked_by_me'] ? 'Unlike' : 'Like'; ?>">
          <span class="post-icon-glyph heart"><?php echo $post['liked_by_me'] ? '♥' : '♡'; ?></span>
          <span class="post-stats-likes"><span class="count"><?php echo (int) $post['like_count']; ?></span><span class="plural" hidden><?php echo (int) $post['like_count'] === 1 ? '' : 's'; ?></span></span>
        </button>
        <button type="button" class="post-icon-btn" title="Comment" onclick="document.getElementById('comment-input-<?php echo (int) $post['id']; ?>').focus();">
          <span class="post-icon-glyph">💬</span>
          <span class="post-stats-comments"><span class="count"><?php echo (int) $post['comment_count']; ?></span><span class="plural" hidden><?php echo (int) $post['comment_count'] === 1 ? '' : 's'; ?></span></span>
        </button>
        <button type="button" class="post-icon-btn post-save-btn<?php echo !empty($post['saved_by_me']) ? ' saved' : ''; ?>" data-save-btn data-post-id="<?php echo (int) $post['id']; ?>" title="<?php echo !empty($post['saved_by_me']) ? 'Remove from saved' : 'Save'; ?>">
          <span class="post-icon-glyph bookmark"><?php echo !empty($post['saved_by_me']) ? '🔖' : '📑'; ?></span>
          <span class="post-stats-saves"><span class="count"><?php echo (int) $post['save_count']; ?></span></span>
        </button>
      </div>
      <div class="post-action-bar-right">
        <a class="post-share-icon fb" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($postUrl); ?>" target="_blank" rel="noopener noreferrer" title="Share to Facebook" aria-label="Share to Facebook">f</a>
        <a class="post-share-icon wa" href="https://wa.me/?text=<?php echo urlencode($postUrl); ?>" target="_blank" rel="noopener noreferrer" title="Share to WhatsApp" aria-label="Share to WhatsApp">✆</a>
        <a class="post-share-icon tg" href="https://t.me/share/url?url=<?php echo urlencode($postUrl); ?>" target="_blank" rel="noopener noreferrer" title="Share to Telegram" aria-label="Share to Telegram">➤</a>
        <button type="button" class="post-share-icon embed" data-copy-link data-post-id="<?php echo (int) $post['id']; ?>" title="Copy embed link">&lt;/&gt;</button>
        <?php if (!$sharedGone): ?>
          <button type="button" class="post-share-icon more" data-share-btn data-post-id="<?php echo (int) $post['id']; ?>" title="More share options" aria-label="More share options">🔁</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="post-copylink-row">
      <input type="text" class="post-copylink-input" id="post-link-<?php echo (int) $post['id']; ?>" value="<?php echo htmlspecialchars($postUrl); ?>" readonly onclick="this.select();" aria-label="Link to this post">
      <button type="button" class="btn post-copylink-btn" data-copy-link data-post-id="<?php echo (int) $post['id']; ?>">Copy link</button>
    </div>

    <div class="post-comments-header" style="<?php echo empty($comments) ? 'display:none;' : ''; ?>" id="post-comments-header-<?php echo (int) $post['id']; ?>">
      Comments (<span class="count"><?php echo (int) $post['comment_count']; ?></span>)
    </div>
    <div class="post-comments" id="post-comments-<?php echo (int) $post['id']; ?>" style="<?php echo empty($comments) ? 'display:none;' : ''; ?>">
      <?php foreach ($comments as $c) echo inkwell_render_comment($c, $post, $user, $isAdmin); ?>
    </div>

    <form class="post-comment-form" data-comment-form data-post-id="<?php echo (int) $post['id']; ?>">
      <span class="post-comment-form-avatar" style="background:<?php echo inkwell_avatar_gradient($grads, $user['id']); ?>;">
        <?php if (!empty($user['avatar'])): ?>
          <img src="/assets/uploads/<?php echo htmlspecialchars($user['avatar']); ?>" alt="" loading="lazy">
        <?php else: ?>
          <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
        <?php endif; ?>
      </span>
      <input type="text" name="comment" id="comment-input-<?php echo (int) $post['id']; ?>" maxlength="500" placeholder="Write a comment…" required>
      <button type="submit" class="post-comment-send" title="Send">➤</button>
    </form>
  </article>
  <?php
  return ob_get_clean();
}

/** Deletes a comment — only its author or an admin may. */
function inkwell_delete_comment($commentId, $userId, $isAdmin = false) {
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT * FROM post_comments WHERE id = ?');
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    if (!$comment) return ['ok' => false, 'error' => 'Comment not found.'];
    if (!$isAdmin && (int) $comment['user_id'] !== (int) $userId) {
      return ['ok' => false, 'error' => 'You can only delete your own comments.'];
    }
    $pdo->prepare('DELETE FROM post_comments WHERE id = ?')->execute([$commentId]);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => 'Could not delete comment (' . $e->getMessage() . ').'];
  }
}

/* -------------------------------------------------------------------
 * Per-photo engagement — Facebook-style: when a post has more than one
 * photo, each individual picture gets its OWN likes and comments,
 * separate from the post's overall like/comment counts shown in the
 * action bar. This lives in post_image_likes / post_image_comments,
 * keyed by post_images.id (see inkwell_get_post_images()), and is
 * surfaced through the fullscreen lightbox (assets/js/post-lightbox.js)
 * since that's where a viewer is looking at one specific photo at a
 * time.
 * ------------------------------------------------------------------- */

/** Fresh per-photo like/comment counts + this viewer's liked state for a batch of image ids, keyed by image id. Mirrors inkwell_get_post_counts() but for post_images rows instead of posts rows. */
function inkwell_get_image_counts($imageIds, $viewerId) {
  $imageIds = array_values(array_unique(array_filter(array_map('intval', (array) $imageIds))));
  if (!$imageIds || !inkwell_posts_image_engagement_tables_ok()) return [];
  try {
    $pdo = inkwell_db();
    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
    $stmt = $pdo->prepare(
      "SELECT pi.id,
              (SELECT COUNT(*) FROM post_image_likes WHERE image_id = pi.id) AS like_count,
              (SELECT COUNT(*) FROM post_image_comments WHERE image_id = pi.id) AS comment_count,
              (SELECT COUNT(*) FROM post_image_likes WHERE image_id = pi.id AND user_id = ?) AS liked_by_me
       FROM post_images pi WHERE pi.id IN ($placeholders)"
    );
    $stmt->execute(array_merge([$viewerId], $imageIds));
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
      $out[(int) $row['id']] = [
        'like_count' => (int) $row['like_count'],
        'comment_count' => (int) $row['comment_count'],
        'liked_by_me' => (bool) $row['liked_by_me'],
      ];
    }
    return $out;
  } catch (PDOException $e) {
    return [];
  }
}

/** Toggles a like on/off for this user on ONE photo (not the whole post). Returns the new liked state and that photo's count — same shape as inkwell_toggle_post_like(). */
function inkwell_toggle_image_like($imageId, $userId) {
  inkwell_ensure_posts_tables();
  if (!inkwell_posts_image_engagement_tables_ok()) {
    return ['ok' => false, 'error' => inkwell_posts_migration_hint()];
  }
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT id FROM post_image_likes WHERE image_id = ? AND user_id = ?');
    $stmt->execute([$imageId, $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
      $pdo->prepare('DELETE FROM post_image_likes WHERE id = ?')->execute([$existing['id']]);
      $liked = false;
    } else {
      $pdo->prepare('INSERT INTO post_image_likes (image_id, user_id) VALUES (?, ?)')->execute([$imageId, $userId]);
      $liked = true;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM post_image_likes WHERE image_id = ?');
    $stmt->execute([$imageId]);
    $count = (int) $stmt->fetchColumn();
    return ['ok' => true, 'liked' => $liked, 'count' => $count];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_posts_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** All comments left on ONE photo, oldest first — same shape/ordering as inkwell_list_comments(). */
function inkwell_list_image_comments($imageId) {
  if (!inkwell_posts_image_engagement_tables_ok()) return [];
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare(
      "SELECT c.*, u.name AS author_name, u.avatar AS author_avatar
       FROM post_image_comments c JOIN users u ON u.id = c.user_id
       WHERE c.image_id = ? ORDER BY c.created_at ASC, c.id ASC"
    );
    $stmt->execute([$imageId]);
    return $stmt->fetchAll();
  } catch (PDOException $e) {
    return [];
  }
}

/** Adds a comment scoped to ONE photo. $imageId must be a real post_images.id (checked via a lightweight lookup so a stale/bogus id can't insert an orphan comment). */
function inkwell_add_image_comment($imageId, $userId, $text) {
  inkwell_ensure_posts_tables();
  $text = trim((string) $text);
  if ($text === '') return ['ok' => false, 'error' => 'Write something before posting a comment.'];
  if (!inkwell_posts_image_engagement_tables_ok()) {
    return ['ok' => false, 'error' => inkwell_posts_migration_hint()];
  }
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT id FROM post_images WHERE id = ?');
    $stmt->execute([$imageId]);
    if (!$stmt->fetch()) return ['ok' => false, 'error' => 'Photo not found.'];

    $stmt = $pdo->prepare('INSERT INTO post_image_comments (image_id, user_id, comment, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$imageId, $userId, $text, date('Y-m-d H:i:s')]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => inkwell_posts_migration_hint() . ' (' . $e->getMessage() . ')'];
  }
}

/** Deletes a per-photo comment — only its author or an admin may. */
function inkwell_delete_image_comment($commentId, $userId, $isAdmin = false) {
  try {
    $pdo = inkwell_db();
    $stmt = $pdo->prepare('SELECT * FROM post_image_comments WHERE id = ?');
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    if (!$comment) return ['ok' => false, 'error' => 'Comment not found.'];
    if (!$isAdmin && (int) $comment['user_id'] !== (int) $userId) {
      return ['ok' => false, 'error' => 'You can only delete your own comments.'];
    }
    $pdo->prepare('DELETE FROM post_image_comments WHERE id = ?')->execute([$commentId]);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => 'Could not delete comment (' . $e->getMessage() . ').'];
  }
}

/** Renders one per-photo comment's markup for the lightbox panel — same visual language as inkwell_render_comment() but scoped to $imageId instead of a post. */
function inkwell_render_image_comment($c, $imageId, $user, $isAdmin) {
  $grads = inkwell_post_avatar_gradients();
  $cGrad = inkwell_avatar_gradient($grads, (int) $c['user_id']);
  ob_start();
  ?>
  <div class="post-comment" id="img-comment-<?php echo (int) $c['id']; ?>">
    <span class="post-comment-avatar" style="background:<?php echo $cGrad; ?>;">
      <?php if (!empty($c['author_avatar'])): ?>
        <img src="/assets/uploads/<?php echo htmlspecialchars($c['author_avatar']); ?>" alt="" loading="lazy">
      <?php else: ?>
        <?php echo strtoupper(substr($c['author_name'], 0, 1)); ?>
      <?php endif; ?>
    </span>
    <div class="post-comment-col">
      <span class="post-comment-author"><?php echo htmlspecialchars($c['author_name']); ?></span>
      <div class="post-comment-body"><?php echo htmlspecialchars($c['comment']); ?></div>
      <div class="post-comment-foot">
        <span class="post-comment-time"><?php echo htmlspecialchars(inkwell_time_ago($c['created_at'])); ?></span>
        <?php if ($isAdmin || (int) $c['user_id'] === (int) $user['id']): ?>
          <span>·</span>
          <button type="button" class="post-comment-del" data-image-comment-delete data-comment-id="<?php echo (int) $c['id']; ?>" data-image-id="<?php echo (int) $imageId; ?>">Delete</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
