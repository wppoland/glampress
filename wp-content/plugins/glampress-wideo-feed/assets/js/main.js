document.addEventListener('DOMContentLoaded', function () {
    const wideoFeedContainer = document.querySelector('.gpf-wideo-feed');

    if (wideoFeedContainer && window.innerWidth <= 1024) {
        document.body.classList.add('gpf-feed-active');

        // --- Global State ---
        let isGloballyMuted = true;
        let timelineVisibilityTimer = null; // Timer for hiding the timeline

        const swiper = new Swiper('.gpf-wideo-feed', {
            direction: 'vertical',
            loop: false,
            mousewheel: true,
            on: {
                init: function() {
                    setupSlide(this.slides[this.activeIndex]);
                    playActiveVideo(this);
                },
                slideChange: function () {
                    // When slide changes, ensure the old timeline timer is cleared
                    clearTimeout(timelineVisibilityTimer);
                    // Also ensure all timelines are hidden
                    document.querySelectorAll('.gpf-timeline-container').forEach(tc => tc.classList.remove('visible'));
                    
                    pauseAllVideos();
                    playActiveVideo(this);
                },
            },
        });

        function playActiveVideo(swiperInstance) {
            const activeSlide = swiperInstance.slides[swiperInstance.activeIndex];
            if (!activeSlide) return;
            
            const video = activeSlide.querySelector('video');
            if (video) {
                video.muted = isGloballyMuted;
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => console.log("Autoplay was prevented.", error));
                }
            }
        }

        function pauseAllVideos() {
            document.querySelectorAll('.gpf-wideo-feed video').forEach(video => {
                video.pause();
            });
        }
        
        function setupSlide(slide) {
            if (slide.dataset.initialized) return;

            const video = slide.querySelector('video');
            const videoWrapper = slide.querySelector('.gpf-video-wrapper');
            const playPauseIcon = slide.querySelector('.gpf-play-pause-icon');
            const timelineContainer = slide.querySelector('.gpf-timeline-container');
            const progressBar = slide.querySelector('.gpf-progress-bar');
            const progress = slide.querySelector('.gpf-progress');
            const currentTimeEl = slide.querySelector('.gpf-current-time');
            const durationEl = slide.querySelector('.gpf-duration');
            const shareButton = slide.querySelector('.gpf-share-button');
            const shareOverlay = slide.querySelector('.gpf-share-overlay');
            const closeShareButton = slide.querySelector('.gpf-close-share');
            const copyLinkButton = slide.querySelector('.gpf-copy-link-button');

            // 1. Tap to Pause/Play (with Timeline visibility logic)
            if (videoWrapper) {
                videoWrapper.addEventListener('click', () => {
                    // Clear any existing timer before making a change
                    clearTimeout(timelineVisibilityTimer);

                    if (video.paused) {
                        video.play();
                        // Hide timeline immediately on play
                        timelineContainer.classList.remove('visible');
                    } else {
                        video.pause();
                        // Show pause icon
                        playPauseIcon.classList.add('visible');
                        setTimeout(() => {
                            playPauseIcon.classList.remove('visible');
                        }, 2000);

                        // Show timeline
                        timelineContainer.classList.add('visible');
                        // Set a timer to hide the timeline after 6 seconds
                        timelineVisibilityTimer = setTimeout(() => {
                            timelineContainer.classList.remove('visible');
                        }, 6000);
                    }
                });
            }

            // 2. Timeline and Time Display (No changes here)
            function formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return `${minutes}:${secs < 10 ? '0' : ''}${secs}`;
            }

            if(video) {
                video.addEventListener('loadedmetadata', () => {
                    durationEl.textContent = formatTime(video.duration);
                });

                video.addEventListener('timeupdate', () => {
                    currentTimeEl.textContent = formatTime(video.currentTime);
                    const progressPercent = (video.currentTime / video.duration) * 100;
                    progress.style.width = `${progressPercent}%`;
                });
            }

            if (progressBar) {
                progressBar.addEventListener('click', (e) => {
                    const rect = progressBar.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const width = rect.width;
                    const duration = video.duration;
                    video.currentTime = (clickX / width) * duration;
                });
            }

            // 3. Share Functionality (No changes here)
            if(shareButton) {
                shareButton.addEventListener('click', () => shareOverlay.classList.add('active'));
            }
            if(closeShareButton) {
                closeShareButton.addEventListener('click', () => shareOverlay.classList.remove('active'));
            }
            if(copyLinkButton) {
                copyLinkButton.addEventListener('click', () => {
                    const urlToCopy = slide.dataset.url;
                    navigator.clipboard.writeText(urlToCopy).then(() => {
                        copyLinkButton.textContent = 'Skopiowano!';
                        setTimeout(() => copyLinkButton.textContent = 'Kopiuj link', 2000);
                    }).catch(err => console.error('Failed to copy text: ', err));
                });
            }

            slide.dataset.initialized = 'true';
        }

        const muteToggleButton = document.querySelector('.gpf-mute-toggle');
        if (muteToggleButton) {
            const iconUnmuted = muteToggleButton.querySelector('.gpf-icon-unmuted');
            const iconMuted = muteToggleButton.querySelector('.gpf-icon-muted');
            muteToggleButton.addEventListener('click', () => {
                isGloballyMuted = !isGloballyMuted;
                const activeVideo = swiper.slides[swiper.activeIndex]?.querySelector('video');
                if (activeVideo) activeVideo.muted = isGloballyMuted;
                iconUnmuted.style.display = isGloballyMuted ? 'none' : 'block';
                iconMuted.style.display = isGloballyMuted ? 'block' : 'none';
            });
        }
        
        swiper.slides.forEach(slide => setupSlide(slide));
    }
});