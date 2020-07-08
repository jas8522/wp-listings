jQuery(document).ready(function($) {
    // Google My Business oauth handling
    if ( window.location.href.indexOf( 'refresh_token=' ) !== -1 && window.location.href.indexOf( 'access_token=' ) !== -1 ) {
        jQuery('.wpl-gmb-login-button-container').html('<span class="dashicons dashicons-update wpl-dashicon"></span>').fadeIn('fast');
        var currentUrl = new URL(window.location.href);
        var accessToken = currentUrl.searchParams.get('access_token');
        var refreshToken = currentUrl.searchParams.get('refresh_token');
        jQuery.post(
            ajaxurl, {
            action: 'wpl_gmb_set_initial_tokens',
            'refresh_token': refreshToken,
            'access_token': accessToken,
            'nonce': wp_listings_admin_integrations['nonce-gmb-initial-tokens']
          }, function (response) { 
            // remove parameters after post.
            if ( window.location.href.split('&code=')[0] ) {
                window.location = window.location.href.split('&refresh_token=')[0] + '#tab-gmb-settings'
            }
          }
        )
    }
    // Reset next post time to tomorrow
    $( '#wpl-reset-next-post-time-button' ).click(
        function(event) {
            event.preventDefault();
            var currentText = jQuery('#wpl-gmb-next-post-label').text();
            jQuery('#wpl-gmb-next-post-label').html('<span class="dashicons dashicons-update wpl-dashicon"></span>').fadeIn('fast');
            jQuery.get(
                ajaxurl, {
                    action: 'wpl_reset_next_post_time_request',
                    nonce: wp_listings_admin_integrations['nonce-gmb-reset-post-time'],
                }, function (response) {
                  if (response) {
                    jQuery('#wpl-gmb-next-post-label').text(response);
                  } else {
                    jQuery('#wpl-gmb-next-post-label').text(currentText);
                  }
                }
            )
        }
    );
    // Clear Google My Business settings button
    $( '#wpl-gmb-clear-settings-button' ).click(
        function(event) {
            event.preventDefault();
            jQuery.get(
                ajaxurl, {
                    action: 'wpl_clear_gmb_settings',
                    nonce: wp_listings_admin_integrations['nonce-gmb-clear-settings'],
                }, function (response) {
                    switch (response) {
                        case 'check permissions':
                            jQuery('#wpl-gmb-clear-settings-button').text('Manage Categories permission required to make this change.');
                            jQuery('#wpl-gmb-clear-settings-button').removeAttr('href');
                            break;
                        case 'request failed':
                            jQuery('#wpl-gmb-clear-settings-button').text('Nonce check failed. Hard refresh the page and try again.');
                            jQuery('#wpl-gmb-clear-settings-button').removeAttr('href');
                            break;
                        default:
                            location.reload();
                    }
                }
            )
        }
    );

    // Save GMB preferences button.
    $( '#wpl-save-gmb-settings-button' ).click(
        function(event) {
          event.preventDefault();
          var gmbSettings = {};
          gmbSettings['locations'] = {};

          // Prep options.
          // general settings
          var postingSettings = {};
          // posting frequency
          switch( document.querySelector('#wpl-gmb-update-frequency-slider').value ) {
            case "0":
              postingSettings['posting_frequency'] = 'weekly';
              break;
            case "50":
              postingSettings['posting_frequency'] = 'biweekly';
              break;
            case "100":
              postingSettings['posting_frequency'] = 'monthly';
              break;
            default:
              postingSettings['posting_frequency'] = 'weekly';
          }
          // use listing data
          postingSettings['empty_schedule_auto_post'] = ( document.querySelector('#wpl-gmb-use-listing-data-checkbox').checked ? 1 : 0 );
          // end of general settings

          // default share settings
          var defaultShareSettings = {};
          // default field values
          defaultShareSettings['default_link'] = document.querySelector('#wpl-gmb-default-link-field').value;
          defaultShareSettings['default_photo'] = document.querySelector('#wpl-gmb-default-photo-field').value;
          defaultShareSettings['default_summary'] = document.querySelector('#wpl-gmb-default-content-field').value;

          // override checkboxes
          defaultShareSettings['default_link_override'] = ( document.querySelector('#wpl-gmb-post-link-override-checkbox').checked ? 1 : 0 );
          defaultShareSettings['default_photo_override'] = ( document.querySelector('#wpl-gmb-post-photo-override-checkbox').checked ? 1 : 0 );
          defaultShareSettings['default_summary_override'] = ( document.querySelector('#wpl-gmb-post-content-override-checkbox').checked ? 1 : 0 );

          // end of default share settings

          // location share settings
          var locationSelections = {}
          document.querySelectorAll('.wpl-gmb-location-tag input').forEach(function(element){
            locationSelections[element.id] = {
              'share_to_location': (element.checked ? 1 : 0)
            }
          })
          // end share location settings

          // populate settings object
          gmbSettings['locations'] = locationSelections;
          gmbSettings['posting_settings'] = postingSettings;
          gmbSettings['posting_defaults'] = defaultShareSettings;

          jQuery('#wpl-save-gmb-settings-button').attr("disabled", true);
          jQuery('#wpl-update-gmb-settings-message').html('<span class="dashicons dashicons-update wpl-dashicon"></span>').fadeIn('fast');

          jQuery.post(
                ajaxurl, {
                    action: 'wpl_update_gmb_preferences',
                    nonce: wp_listings_admin_integrations['nonce-gmb-update-settings'],
                    settings: gmbSettings
                }, function (response) {
                    switch (response) {
                        case 'success':
                            jQuery('#wpl-update-gmb-settings-message').text('Update successful.').fadeIn('slow');
                            break;
                        case 'check permissions':
                            jQuery('#wpl-update-gmb-settings-message').text('Manage Categories permission required to make this change.').fadeIn('slow');
                            break;
                        case 'request failed':
                            jQuery('#wpl-update-gmb-settings-message').text('Invalid nonce. Hard refresh the page and try again.').fadeIn('slow');
                            break;
                        default:
                            location.reload()
                    }
                    jQuery('#wpl-update-gmb-settings-message').fadeOut(2000);
                    jQuery( '#wpl-save-gmb-settings-button' ).attr("disabled", false);
                }
            )

        }
    );

    $( '#wpl-gmb-update-frequency-slider' ).change(
        function(event) {
            if (event.target.value == 0){
                jQuery('#wpl-gmb-update-frequency-label').text('Weekly');
            }

            if (event.target.value == 50){
                jQuery('#wpl-gmb-update-frequency-label').text('Biweekly');
            }

            if (event.target.value == 100){
                jQuery('#wpl-gmb-update-frequency-label').text('Monthly');
            }
        }
    );


    // Populate preview if first schedule cell is populated.
    if (document.querySelector('#dropzone-0 > *')) {
      document.querySelector('.preview-cell-text-area').innerText = document.querySelector('#dropzone-0').firstElementChild.dataset.summary;
      document.querySelector('.preview-cell-photo').style.backgroundImage = document.querySelector('#dropzone-0').firstElementChild.style.backgroundImage;
    }

})

// Login lightbox functions
function agreeToTermsChecked(element) {
  if (element.checked) {
    document.getElementById('agree-to-terms-button').removeAttribute('disabled');
  } else {
    document.getElementById('agree-to-terms-button').setAttribute('disabled', '');
  }
}

function cancelLoginClicked() {
  hideLightbox();
}

function showLightbox(){
  document.getElementById('terms-lightbox').classList.add('lightbox-active');
}

function hideLightbox(){
  document.getElementById('terms-lightbox').classList.remove('lightbox-active');
}

// Schedule view functions
function addToScheduleClicked(id) {
  for( let i = 0; i < 12; i++ ){
    if (document.getElementById('dropzone-' + i).hasChildNodes()) {
      continue;
    }
    document.getElementById('dropzone-' + i).appendChild(
      document.getElementById(id)
    );
    document.getElementById('wpl-schedule-save-status').textContent = 'Unsaved Changes';
    updatePostPreview();
    return;
  }
  document.getElementById('wpl-schedule-save-status').innerHTML = 'Clear out space in schedule to add more listings.';
}

function removeFromScheduleClicked(id) {
  let parentElement = document.getElementById(id).parentNode
  document.getElementById('active-listings-container').prepend(
    document.getElementById(id)
  );
  // Removing any blank text that .prepend() can leave behind as this will trigger a true response when hasChildren() is called.
  parentElement.innerHTML = "";
  updatePostPreview();
  document.getElementById('wpl-schedule-save-status').textContent = 'Unsaved Changes';
}

function excludeListingClicked(id) {
  document.getElementById('hidden-listings-container').prepend(
    document.getElementById(id)
  );
  postExclusionListChange(id, 'add');
}

function removedFromExludeListClicked(id) {
  document.getElementById('active-listings-container').prepend(
    document.getElementById(id)
  );
  postExclusionListChange(id, 'remove');
}


function onDragStart(event) {
  event.dataTransfer.setData('text/plain', event.target.id);
}

function onDrop(event) {
  const id = event.dataTransfer.getData('text');
  const draggableElement = document.getElementById(id);
  const originalParentContainerId = draggableElement.parentNode.id;
  var dropzone = event.target;

  // Check if cell is dropped on a populated dropzone.
  if ( dropzone.classList.contains('draggable') ) {
    dropzone = dropzone.parentNode;
  }

  if ( dropzone.classList.contains('schedule-dropzone') && dropzone.firstElementChild ) {
    const draggableParent = draggableElement.parentNode;
    const currentChild = dropzone.firstElementChild;
    dropzone.replaceChild(draggableElement, currentChild);
    draggableParent.appendChild(currentChild);
  }
  dropzone.prepend(draggableElement);
  event.dataTransfer.clearData();
  updatePostPreview();

  // Update save status label if cell was added or removed from scheduled area.
  if ( originalParentContainerId.includes('dropzone-') || dropzone.id.includes('dropzone-') ) {
    document.getElementById('wpl-schedule-save-status').textContent = 'Unsaved Changes';
  }

  // If listing is removed from excluded zone.
  if ( 'hidden-listings-container' === originalParentContainerId ) {
    postExclusionListChange(id, 'remove');
  }
  // If listing is added to excluded zone.
  if ( 'hidden-listings-container' === dropzone.id ) {
    postExclusionListChange(id, 'add');
  }
}

function onDragOver(event) {
  event.preventDefault();
}

function clearExclusionList() {
  var confirmation = confirm("Remove all posts from the exclusion list?");
  if (confirmation) {
    postExclusionListChange('', 'clear');
  }
}

function postExclusionListChange(post_id, update_type) {
  jQuery.post(
    ajaxurl, {
      action: 'wpl_update_exclusion_list',
      nonce: wp_listings_admin_integrations['nonce-gmb-update-exclusion-list'],
      post_id: post_id,
      update_type: update_type,
    }, function (response) {
      // If all excluded listings are cleared, reload the page instead of trying to move them all. 
      if ('success' === response && 'clear' === update_type) {
        location.reload();
      }
    }
  )
}

function postNextScheduledNow() {
  // If there are unsaved schedule changes, throw alert and return.
  if (document.getElementById('wpl-schedule-save-status').innerHTML === 'Unsaved Changes') {
    document.getElementById('wpl-schedule-save-status').innerHTML = "Save schedule changes before publishing next post";
    return;
  }

  var confirmation = confirm("Post next scheduled post now?");
  if (confirmation) {
    jQuery.post(
      ajaxurl, {
        action: 'wpl_post_next_scheduled_now',
        nonce: wp_listings_admin_integrations['nonce-gmb-post-next-scheduled-now'],
      }, function (response) {
        location.reload();
      }
    )
  }
}

function updateScheduledPosts() {
  var scheduledPosts = [];

  document.querySelectorAll('.schedule-dropzone').forEach(function(value){
    if (value.firstElementChild){
      scheduledPosts.push(value.firstElementChild.id);
    }
  });

  document.getElementById('wpl-schedule-save-status').innerHTML = '<span class="dashicons dashicons-update wpl-dashicon"></span>';
  jQuery.post(
    ajaxurl, {
      action: 'wpl_update_scheduled_posts',
      nonce: wp_listings_admin_integrations['nonce-gmb-update-scheduled-posts'],
      scheduled_posts: scheduledPosts,
    }, function (response) {
      if ('success' === response) {
        document.getElementById('wpl-schedule-save-status').textContent = 'Saved';
      }
    }
  )
}

function clearScheduledPosts() {
  var confirmation = confirm("Clear all currently scheduled posts?");
  if (confirmation) {
    jQuery.post(
      ajaxurl, {
        action: 'wpl_clear_scheduled_posts',
        nonce: wp_listings_admin_integrations['nonce-gmb-clear-scheduled-posts'],
      }, function (response) {
        location.reload();
      }
    )
  }
}

function clearLastPostStatus() {
  var confirmation = confirm("Clear last post status?");
  if (confirmation) {
    jQuery.post(
      ajaxurl, {
        action: 'wpl_clear_last_post_status',
        nonce: wp_listings_admin_integrations['nonce-gmb-clear-last-post-status'],
      }, function (response) {
        location.reload();
      }
    )
  }
}
