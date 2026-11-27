</main>
<?php if (!empty($u)): ?></div></div><?php endif; ?>
<?php if (!empty($u) && !empty($userProfile)): ?>
<div class="modal" id="profile-modal">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-dialog modal-profile" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">
    <form id="profile-modal-form" class="pm-form" autocomplete="off">
      <div class="pm-shell">
        <aside class="pm-identity-col">
          <div class="pm-spotlight">
            <div class="pm-avatar-preview-wrap">
              <?= renderAvatar($userProfile, 'avatar-xl', false) ?>
              <button type="button" class="pm-avatar-crop-fab" id="pm-crop-fab" title="Crop photo" aria-label="Crop photo"<?= $userProfile['avatar_type'] === 'image' ? '' : ' hidden' ?>><?= icon('crop', 12) ?></button>
            </div>
            <h4 class="pm-spot-name" data-pm-preview-name><?= e($userProfile['full_name']) ?></h4>
            <p class="pm-spot-email"><?= e($userProfile['email']) ?></p>
            <span class="pm-role-tag"><span class="pm-status-dot" aria-hidden="true"></span><span><?= e(strtoupper($userProfile['role'])) ?></span></span>

            <div class="pm-shape-toggle" role="group" aria-label="Avatar shape">
              <button type="button" class="pm-shape-btn<?= $userProfile['avatar_shape'] === 'badge' ? ' is-active' : '' ?>" data-pm-shape="badge">
                <span class="pm-shape-ic pm-shape-sq" aria-hidden="true"></span><span>Badge</span>
              </button>
              <button type="button" class="pm-shape-btn<?= $userProfile['avatar_shape'] !== 'badge' ? ' is-active' : '' ?>" data-pm-shape="circle">
                <span class="pm-shape-ic pm-shape-ci" aria-hidden="true"></span><span>Circle</span>
              </button>
            </div>
            <p class="pm-avatar-spec" id="pm-avatar-spec">
              <?= $userProfile['avatar_shape'] === 'badge' ? 'Badge' : 'Circle' ?> &middot; 256&times;256px
            </p>
          </div>

          <div class="pm-meta-list">
            <div class="pm-meta-item">
              <span class="pm-meta-k"><?= icon('phone', 13) ?><span>Mobile</span></span>
              <span class="pm-meta-v" id="pm-meta-phone"><?= e($userProfile['phone'] ?: 'Not set') ?></span>
            </div>
            <div class="pm-meta-item">
              <span class="pm-meta-k"><?= icon('pin', 13) ?><span>Location</span></span>
              <span class="pm-meta-v" id="pm-meta-address"><?= e($userProfile['address'] ?: 'Not set') ?></span>
            </div>
            <?php if (!empty($userProfile['created_at'])): ?>
              <div class="pm-meta-item">
                <span class="pm-meta-k"><?= icon('clock', 13) ?><span>Joined</span></span>
                <span class="pm-meta-v"><?= e(fmtDate($userProfile['created_at'])) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </aside>

        <div class="pm-workspace">
          <div class="pm-top-bar">
            <div class="pm-top-head">
              <div>
                <h3 id="profile-modal-title">Edit Profile &amp; Account</h3>
                <p class="muted">Customize your avatar, barangay contact details, and password</p>
              </div>
              <button type="button" class="notif-head-ic" data-modal-close aria-label="Close dialog"><?= icon('x', 16) ?></button>
            </div>

            <nav class="pm-tabs" role="tablist" aria-label="Profile sections">
              <span class="pm-tab-slider" id="pm-tab-slider" aria-hidden="true"></span>
              <button type="button" class="pm-tab-btn is-active" role="tab" aria-selected="true" data-pm-tab="identity" data-pm-step="1">
                <?= icon('user', 15) ?><span>Profile &amp; Avatar</span>
              </button>
              <button type="button" class="pm-tab-btn" role="tab" aria-selected="false" data-pm-tab="contact" data-pm-step="2">
                <?= icon('pin', 15) ?><span>Contact &amp; Barangay</span>
              </button>
              <button type="button" class="pm-tab-btn" role="tab" aria-selected="false" data-pm-tab="security" data-pm-step="3">
                <?= icon('lock', 15) ?><span>Security</span>
              </button>
            </nav>
          </div>

          <div class="pm-main">
            <div class="flash error" id="profile-modal-err" hidden></div>

            <section class="pm-pane is-active" data-pm-pane="identity" role="tabpanel">
              <div class="pm-section-box">
                <div class="form-row pm-fields-row">
                  <div>
                    <label class="pm-lbl" for="pm-name"><?= icon('user', 14) ?><span>Display Name</span></label>
                    <div class="pm-input-ic">
                      <?= icon('user', 16) ?>
                      <input id="pm-name" name="full_name" required value="<?= e($userProfile['full_name']) ?>">
                    </div>
                  </div>
                  <div>
                    <label class="pm-lbl" for="pm-email"><?= icon('mail', 14) ?><span>Account Email</span></label>
                    <div class="pm-input-ic is-disabled">
                      <?= icon('mail', 16) ?>
                      <input id="pm-email" type="email" value="<?= e($userProfile['email']) ?>" disabled>
                    </div>
                  </div>
                </div>
              </div>

              <div class="pm-section-box">
                <div class="pm-studio-head">
                  <div>
                    <h4>Avatar Source</h4>
                    <p class="muted">Upload an image, capture via webcam, link a URL, or select a badge style</p>
                  </div>
                  <span class="pm-mode-tag" id="pm-avatar-mode"><?= $userProfile['avatar_type'] === 'image' ? 'Custom Photo' : 'Badge Mode' ?></span>
                </div>

                <div class="pm-photo-actions">
                  <input type="file" id="pm-photo-file" accept="image/png,image/jpeg,image/webp" hidden>
                  <button type="button" class="btn small pm-btn-upload" id="pm-upload-btn"><?= icon('upload', 14) ?><span>Upload Photo</span></button>
                  <button type="button" class="btn ghost small" id="pm-camera-btn"><?= icon('camera', 14) ?><span>Take Photo</span></button>
                  <button type="button" class="btn ghost small" id="pm-crop-btn"<?= $userProfile['avatar_type'] === 'image' ? '' : ' disabled' ?>><?= icon('crop', 14) ?><span>Crop</span></button>
                  <button type="button" class="btn ghost small" id="pm-url-toggle-btn"><?= icon('link', 14) ?><span>Image URL</span></button>
                  <button type="button" class="pm-trash-btn" id="pm-reset-avatar-btn" title="Remove photo and reset to badge" aria-label="Reset avatar to badge"><?= icon('trash', 14) ?></button>
                </div>

                <div class="pm-subpanel" id="pm-camera-panel" hidden>
                  <div class="pm-subpanel-head">
                    <strong><?= icon('camera', 15) ?> Camera Capture</strong>
                    <button type="button" class="notif-head-ic" id="pm-camera-close" aria-label="Close camera"><?= icon('x', 14) ?></button>
                  </div>
                  <p class="muted pm-cam-err" id="pm-cam-err" hidden></p>
                  <div class="pm-cam-stage">
                    <video id="pm-cam-video" autoplay playsinline muted></video>
                  </div>
                  <div class="pm-subpanel-actions">
                    <button type="button" class="btn ghost small" id="pm-camera-cancel">Close</button>
                    <button type="button" class="btn small" id="pm-camera-snap"><?= icon('camera', 14) ?> Capture Photo</button>
                  </div>
                </div>

                <div class="pm-subpanel" id="pm-crop-panel" hidden>
                  <div class="pm-subpanel-head">
                    <strong><?= icon('crop', 15) ?> Crop Avatar (256&times;256)</strong>
                    <button type="button" class="notif-head-ic" id="pm-crop-close" aria-label="Close cropper"><?= icon('x', 14) ?></button>
                  </div>
                  <div class="pm-crop-layout">
                    <canvas id="pm-crop-canvas" width="200" height="200"></canvas>
                    <div class="pm-crop-controls">
                      <label for="pm-crop-zoom">Zoom</label>
                      <input type="range" id="pm-crop-zoom" min="1" max="3" step="0.05" value="1">
                      <label for="pm-crop-x">Horizontal</label>
                      <input type="range" id="pm-crop-x" min="-100" max="100" step="1" value="0">
                      <label for="pm-crop-y">Vertical</label>
                      <input type="range" id="pm-crop-y" min="-100" max="100" step="1" value="0">
                    </div>
                  </div>
                  <div class="pm-subpanel-actions">
                    <button type="button" class="btn ghost small" id="pm-crop-cancel">Close</button>
                    <button type="button" class="btn small" id="pm-crop-apply"><?= icon('check-simple', 14) ?> Apply Crop</button>
                  </div>
                </div>

                <div class="pm-subpanel" id="pm-url-panel" hidden>
                  <div class="pm-subpanel-head">
                    <strong><?= icon('link', 15) ?> Use Image URL</strong>
                    <button type="button" class="notif-head-ic" id="pm-url-close" aria-label="Close URL input"><?= icon('x', 14) ?></button>
                  </div>
                  <div class="pm-url-row">
                    <input type="url" id="pm-avatar-url-input" placeholder="https://example.com/avatar.jpg" value="<?= e($userProfile['avatar_type'] === 'image' && preg_match('#^https?://#i', (string) ($userProfile['avatar_url'] ?? '')) ? $userProfile['avatar_url'] : '') ?>">
                    <button type="button" class="btn small" id="pm-url-apply">Apply</button>
                  </div>
                </div>

                <?php $styles = avatarBadgeStyles(); ?>
                <div class="pm-badge-head">
                  <span>Quick badge styles</span>
                  <small><?= count($styles) ?> presets</small>
                </div>
                <div class="pm-style-grid" id="pm-badge-grid">
                  <?php foreach ($styles as $bs): ?>
                    <button type="button"
                      class="pm-style-tile<?= ($userProfile['avatar_type'] !== 'image' && ($userProfile['avatar_style'] ?: 'initials') === $bs['id']) ? ' is-selected' : '' ?>"
                      data-pm-badge="<?= e($bs['id']) ?>">
                      <span class="pm-badge-dot av-style-<?= e($bs['id']) ?><?= ($bs['kind'] ?? '') === 'image' ? ' av-has-img' : '' ?>">
                        <?php if (($bs['kind'] ?? '') === 'image'): ?>
                          <img class="pm-badge-img" src="<?= e(BASE_URL . $bs['img']) ?>" alt="<?= e($bs['label']) ?> badge" referrerpolicy="no-referrer">
                        <?php elseif (($bs['kind'] ?? '') === 'icon'): ?>
                          <?= icon($bs['ic'], 13) ?>
                        <?php else: ?>
                          <span data-user-initials><?= e(initials($userProfile['full_name'])) ?></span>
                        <?php endif; ?>
                      </span>
                      <span class="pm-style-label"><?= e($bs['label']) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>

            <section class="pm-pane" data-pm-pane="contact" role="tabpanel" hidden>
              <div class="pm-section-box">
                <div class="pm-pane-intro">
                  <span class="pm-pane-ic"><?= icon('pin', 18) ?></span>
                  <div>
                    <h4>Contact &amp; Barangay Address</h4>
                    <p class="muted">Used by barangay responders for report follow-ups and emergency dispatch coordination.</p>
                  </div>
                </div>
                <div class="form-row pm-fields-row">
                  <div>
                    <label class="pm-lbl" for="pm-phone"><?= icon('phone', 14) ?><span>Mobile Number</span></label>
                    <div class="pm-input-ic">
                      <?= icon('phone', 16) ?>
                      <input id="pm-phone" name="phone" placeholder="09XX-XXX-XXXX" value="<?= e($userProfile['phone']) ?>">
                    </div>
                  </div>
                  <div>
                    <label class="pm-lbl" for="pm-address"><?= icon('pin', 14) ?><span>Address / Purok</span></label>
                    <div class="pm-input-ic">
                      <?= icon('pin', 16) ?>
                      <input id="pm-address" name="address" placeholder="Street, Purok, or Barangay Hall" <?= $userProfile['role'] === 'resident' ? 'required' : '' ?> value="<?= e($userProfile['address']) ?>">
                    </div>
                  </div>
                </div>
              </div>

              <div class="pm-context-grid">
                <div class="pm-context-card">
                  <div class="pm-context-head"><?= icon('siren', 15) ?><strong>Emergency SOS Fallback</strong></div>
                  <p class="muted">When GPS signal is unavailable during an SOS alert, your saved Purok or street address serves as the primary dispatch location.</p>
                </div>
                <div class="pm-context-card">
                  <div class="pm-context-head"><?= icon('phone', 15) ?><strong>Responder Reachability</strong></div>
                  <p class="muted">An up-to-date mobile number enables direct SMS and call coordination from barangay officials and volunteers.</p>
                </div>
              </div>
            </section>

            <section class="pm-pane" data-pm-pane="security" role="tabpanel" hidden>
              <div class="pm-section-box">
                <div class="pm-pane-intro">
                  <span class="pm-pane-ic"><?= icon('lock', 18) ?></span>
                  <div>
                    <h4>Password &amp; Credentials</h4>
                    <p class="muted">Leave both fields empty if you want to keep your existing password.</p>
                  </div>
                </div>
                <div class="form-row pm-fields-row">
                  <div>
                    <label class="pm-lbl" for="pm-cur-pw"><?= icon('lock', 14) ?><span>Current Password</span></label>
                    <div class="pm-input-ic pm-pw-ic">
                      <?= icon('lock', 16) ?>
                      <input id="pm-cur-pw" type="password" name="current_password" placeholder="Enter current password">
                      <button type="button" class="pm-eye-btn" data-pm-eye="pm-cur-pw" aria-label="Show password"><?= icon('eye', 16) ?></button>
                    </div>
                  </div>
                  <div>
                    <label class="pm-lbl" for="pm-new-pw"><?= icon('key', 14) ?><span>New Password</span></label>
                    <div class="pm-input-ic pm-pw-ic">
                      <?= icon('key', 16) ?>
                      <input id="pm-new-pw" type="password" name="new_password" minlength="6" placeholder="Min. 6 characters">
                      <button type="button" class="pm-eye-btn" data-pm-eye="pm-new-pw" aria-label="Show password"><?= icon('eye', 16) ?></button>
                    </div>
                  </div>
                </div>
                <div class="pm-pw-meter" id="pm-pw-meter" aria-live="polite">
                  <div class="pm-pw-bar"><span id="pm-pw-fill"></span></div>
                  <small id="pm-pw-hint">Optional &mdash; minimum 6 characters to change password</small>
                </div>
              </div>

              <div class="pm-context-grid">
                <div class="pm-context-card">
                  <div class="pm-context-head"><?= icon('shield', 15) ?><strong>Session Protection</strong></div>
                  <p class="muted">All profile and password updates are CSRF-protected and synchronized immediately across your active session.</p>
                </div>
                <div class="pm-context-card">
                  <div class="pm-context-head"><?= icon('key', 15) ?><strong>Recommended Strength</strong></div>
                  <p class="muted">Combine 8+ characters with uppercase letters, digits, or symbols to keep your account secure.</p>
                </div>
              </div>
            </section>
          </div>

          <div class="pm-footer">
            <span class="pm-sync-note"><?= icon('shield', 15) ?><span>Synced across your CAIRS session</span></span>
            <button type="submit" class="btn" id="pm-save-btn"><?= icon('check-simple', 16) ?><span>Save Changes</span></button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="false">
  <?php if (!empty($flash)): ?>
    <div class="flash <?= e($flash['t']) ?> toast" role="<?= $flash['t'] === 'error' ? 'alert' : 'status' ?>">
      <span class="toast-ic" aria-hidden="true"><?= icon($flash['t'] === 'error' ? 'alert' : 'check', 18) ?></span>
      <div class="toast-body"><?= e($flash['m']) ?></div>
      <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
    </div>
  <?php endif; ?>
</div>
<script src="<?= BASE_URL ?>/assets/js/script.php"></script>
</body>
</html>
