# NotMiro - Collaborative Mindmapping for Nextcloud

NotMiro is a Nextcloud application that provides a collaborative infinite canvas for creating mindmaps. Create multiple independent mindmaps, add formatted text and images to nodes, and collaborate in real-time with other users.

## Features

- **Infinite Canvas**: Pan and zoom to navigate your thoughts
- **Real-time Collaboration**: Work together with others simultaneously
- **Rich Text Editing**: Format your text with bold, italic, headers, and more
- **Image Support**: Add images to your mindmap nodes
- **Automatic Saving**: Changes are automatically saved to your Nextcloud storage
- **User Awareness**: See where other users are working and who's editing what

## Technical Details

NotMiro is built with:

- Vue.js for the frontend UI
- ReactFlow for the infinite canvas and mindmap display
- Y.js for real-time collaboration
- Quill for rich text editing
- Nextcloud Files API for storage
- PHP for the backend

## Installation

1. Clone this repository to your Nextcloud apps directory:
   ```
   cd /path/to/nextcloud/apps/
   git clone https://github.com/yourusername/notmiro.git
   ```

2. Enable the app in Nextcloud:
   - Go to Settings > Apps > Disabled apps
   - Find "NotMiro" and click "Enable"

## Development

### Requirements

- Node.js 20+
- npm 10+
- Nextcloud 29+

### Building the app

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Build for development and watch for changes
npm run watch
```

## License

This app is licensed under the AGPL v3. See the [LICENSE](LICENSE) file for details.

## Author

- Vladislav <nauka.2.0.vs@gmail.com>
- Website: [hello.illegalfiles.icu](https://hello.illegalfiles.icu)
