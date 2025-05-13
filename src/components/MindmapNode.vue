<template>
	<div 
		class="mindmap-node" 
		:class="{ 'locked': isLocked && !isCurrentUserLocked }"
		@click="handleNodeClick"
	>
		<div class="node-header">
			<span v-if="isLocked" class="lock-indicator">
				🔒 {{ lockedBy }}
			</span>
			<div class="node-actions">
				<button @click.stop="handleDeleteNode" class="action-button delete">
					<span>✕</span>
				</button>
			</div>
		</div>
		
		<div class="node-content">
			<!-- Text editor container -->
			<div v-if="isEditing" class="quill-editor" ref="quillEditor"></div>
			
			<!-- Read-only view -->
			<div v-else class="node-text" v-html="nodeData.content || ''"></div>
			
			<!-- Image container, if present -->
			<div v-if="nodeData.image" class="node-image">
				<img :src="nodeData.image" />
			</div>
		</div>
		
		<div class="node-footer">
			<button v-if="!isEditing" @click.stop="startEditing" class="action-button edit">
				<span>✎</span>
			</button>
			<button v-if="isEditing" @click.stop="saveChanges" class="action-button save">
				<span>✓</span>
			</button>
			<button @click.stop="openImagePicker" class="action-button image">
				<span>📷</span>
			</button>
			<button @click.stop="addChildNode" class="action-button add-child">
				<span>+</span>
			</button>
		</div>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import * as Y from 'yjs'
import { QuillBinding } from 'y-quill'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'

export default {
	name: 'MindmapNode',
	props: {
		nodeId: {
			type: String,
			required: true,
		},
		nodeData: {
			type: Object,
			required: true,
		},
		lockedNodes: {
			type: Object,
			required: true,
		},
		yDoc: {
			required: true,
		},
		currentUser: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			isEditing: false,
			quill: null,
			quillBinding: null,
			yText: null,
		}
	},
	computed: {
		isLocked() {
			return !!this.lockedNodes[this.nodeId]
		},
		isCurrentUserLocked() {
			return this.lockedNodes[this.nodeId] === this.currentUser
		},
		lockedBy() {
			return this.lockedNodes[this.nodeId] || ''
		},
	},
	methods: {
		handleNodeClick() {
			this.$emit('select-node', this.nodeId)
		},
		
		startEditing() {
			// Check if node is locked by someone else
			if (this.isLocked && !this.isCurrentUserLocked) {
				showError(this.$t('notmiro', 'This node is being edited by {user}', { user: this.lockedBy }))
				return
			}
			
			// Lock the node for editing
			this.$emit('lock-node', this.nodeId)
			this.isEditing = true
			
			// Initialize Quill editor in the next tick after DOM update
			this.$nextTick(() => {
				this.initQuillEditor()
			})
		},
		
		initQuillEditor() {
			const quillOptions = {
				modules: {
					toolbar: [
						['bold', 'italic', 'underline'],
						[{ 'header': 1 }, { 'header': 2 }],
						[{ 'list': 'ordered' }, { 'list': 'bullet' }],
						[{ 'color': [] }, { 'background': [] }],
						['clean'],
					],
				},
				theme: 'snow',
				placeholder: this.$t('notmiro', 'Enter text here...'),
			}
			
			// Initialize Quill
			this.quill = new Quill(this.$refs.quillEditor, quillOptions)
			
			// Set up Yjs binding for collaborative text editing
			this.yText = this.yDoc.getText(`node-content-${this.nodeId}`)
			
			// Initialize with existing content if available
			if (this.nodeData.content) {
				// For new nodes, set the content from nodeData
				if (this.yText.length === 0) {
					this.quill.root.innerHTML = this.nodeData.content
				}
			}
			
			// Set up binding between quill and yjs
			this.quillBinding = new QuillBinding(this.yText, this.quill)
		},
		
		saveChanges() {
			if (!this.quill) return
			
			const content = this.quill.root.innerHTML
			
			// Update the node data
			this.$emit('update-node', this.nodeId, {
				...this.nodeData,
				content,
			})
			
			// Clean up Quill
			if (this.quillBinding) {
				this.quillBinding.destroy()
				this.quillBinding = null
			}
			this.quill = null
			
			// Stop editing and unlock the node
			this.isEditing = false
			this.$emit('unlock-node', this.nodeId)
		},
		
		handleDeleteNode() {
			if (this.isLocked && !this.isCurrentUserLocked) {
				showError(this.$t('notmiro', 'This node is being edited by {user}', { user: this.lockedBy }))
				return
			}
			
			// Confirm deletion if it has content or children
			if ((this.nodeData.content && this.nodeData.content.length > 0) || 
				(this.nodeData.image)) {
				if (!confirm(this.$t('notmiro', 'Are you sure you want to delete this node?'))) {
					return
				}
			}
			
			this.$emit('delete-node', this.nodeId)
		},
		
		addChildNode() {
			this.$emit('add-child', this.nodeId)
		},
		
		async openImagePicker() {
			if (this.isLocked && !this.isCurrentUserLocked) {
				showError(this.$t('notmiro', 'This node is being edited by {user}', { user: this.lockedBy }))
				return
			}
			
			// Lock the node during image selection
			this.$emit('lock-node', this.nodeId)
			
			try {
				// Set up file picker
				const picker = getFilePickerBuilder(this.$t('notmiro', 'Choose an image'))
					.setMultiSelect(false)
					.setMimeTypeFilter(['image/jpeg', 'image/png', 'image/gif'])
					.setType(1) // files only
					.allowDirectories(false)
					.build()
				
				// Show file picker
				const result = await picker.pick()
				if (result && result.length > 0) {
					const file = result[0]
					
					// Get direct URL to the file
					const imageUrl = generateUrl('/core/preview?fileId={fileId}&x=500&y=500', {
						fileId: file.id,
					})
					
					// Update node with image
					this.$emit('update-node', this.nodeId, {
						...this.nodeData,
						image: imageUrl,
					})
				}
			} catch (error) {
				console.error('Error selecting image:', error)
				showError(this.$t('notmiro', 'Failed to select image'))
			} finally {
				// Unlock the node if we're not editing text
				if (!this.isEditing) {
					this.$emit('unlock-node', this.nodeId)
				}
			}
		},
	},
	beforeDestroy() {
		// Clean up Quill if component is destroyed while editing
		if (this.quillBinding) {
			this.quillBinding.destroy()
		}
		
		// Make sure to unlock the node if we were editing
		if (this.isCurrentUserLocked) {
			this.$emit('unlock-node', this.nodeId)
		}
	},
}
</script>

<style scoped lang="scss">
.mindmap-node {
	background: var(--color-main-background);
	border: 2px solid var(--color-border);
	border-radius: 8px;
	padding: 10px;
	min-width: 200px;
	max-width: 400px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	transition: all 0.2s ease;
	
	&:hover {
		border-color: var(--color-primary);
	}
	
	&.locked {
		opacity: 0.7;
		border-color: var(--color-warning);
		background-color: var(--color-background-hover);
	}
}

.node-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 5px;
}

.lock-indicator {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	display: flex;
	align-items: center;
	gap: 4px;
}

.node-content {
	margin: 10px 0;
	
	.node-text {
		min-height: 20px;
		word-break: break-word;
	}
	
	.node-image {
		margin-top: 10px;
		
		img {
			max-width: 100%;
			max-height: 200px;
			border-radius: 4px;
		}
	}
}

.node-footer, .node-actions {
	display: flex;
	gap: 5px;
}

.action-button {
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: 4px;
	width: 24px;
	height: 24px;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	transition: all 0.2s ease;
	
	&:hover {
		background: var(--color-primary-light);
	}
	
	&.delete:hover {
		background: var(--color-error);
		color: white;
	}
	
	&.save:hover {
		background: var(--color-success);
		color: white;
	}
}

/* Quill editor styles */
.quill-editor {
	border: 1px solid var(--color-border);
	border-radius: 4px;
	
	/* Customize Quill toolbar to match Nextcloud theme */
	:deep(.ql-toolbar) {
		border: none;
		border-bottom: 1px solid var(--color-border);
		background: var(--color-background-hover);
	}
	
	:deep(.ql-container) {
		border: none;
	}
}
</style> 