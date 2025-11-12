# Hero Video Background

## Adding Your Own Video

To add a custom video background to the hero section:

1. **Video Requirements:**
   - Format: MP4 (recommended)
   - Resolution: 1920x1080 or higher
   - Duration: 10-30 seconds (for better loading)
   - File size: Under 10MB for web performance
   - Content: College events, campus life, students, etc.

2. **File Placement:**
   - Place your video file in this directory: `assets/videos/`
   - Rename it to: `college-events.mp4`
   - Or update the source path in `index.php`

3. **Video Optimization Tips:**
   - Use H.264 codec for best browser compatibility
   - Compress the video to reduce file size
   - Consider creating multiple formats (MP4, WebM) for broader support

4. **Fallback System:**
   - The system includes multiple fallback videos
   - If your local video fails to load, it will try online sources
   - If all videos fail, it falls back to the gradient background

## Current Fallback Videos:
- Primary: `assets/videos/college-events.mp4` (your custom video)
- Fallback 1: Big Buck Bunny sample video
- Fallback 2: Sample video from sample-videos.com
- Final fallback: Gradient background

## Recommended Video Sources:
- Record your own college events
- Use royalty-free videos from:
  - Pexels (pexels.com)
  - Unsplash (unsplash.com)
  - Pixabay (pixabay.com)

## Technical Notes:
- Video is hidden on mobile devices for performance
- Autoplay is muted to comply with browser policies
- Video loops continuously
- Overlay is applied for text readability
