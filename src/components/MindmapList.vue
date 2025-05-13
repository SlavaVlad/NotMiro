<template>
	<div class="mindmap-list">
		<div class="list-header">
			<h3>{{ t('notmiro', 'Your Mindmaps') }}</h3>
			<NcButton @click="reloadList">
				<template #icon>↻</template>
				{{ t('notmiro', 'Refresh') }}
			</NcButton>
		</div>
		
		<div v-if="loading" class="empty-state">
			{{ t('notmiro', 'Loading...') }}
		</div>
		
		<div v-else-if="mindmaps.length === 0" class="empty-state">
			{{ t('notmiro', 'No mindmaps yet. Create one using the "New Mindmap" button.') }}
		</div>
		
		<ul v-else class="mindmap-items">
			<li 
				v-for="mindmap in sortedMindmaps" 
				:key="mindmap.name"
				class="mindmap-item"
				:class="{ 'active': selectedMindmap === mindmap.name }"
				@click="selectMindmap(mindmap.name)"
			>
				<div class="mindmap-info">
					<div class="mindmap-name">{{ formatName(mindmap.name) }}</div>
					<div class="mindmap-date">{{ formatDate(mindmap.mtime) }}</div>
				</div>
				<div class="mindmap-actions">
					<button 
						class="action-button delete" 
						@click.stop="deleteMindmap(mindmap.name)"
						:title="t('notmiro', 'Delete mindmap')"
					>
						✕
					</button>
				</div>
			</li>
		</ul>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'MindmapList',
	components: {
		NcButton,
	},
	props: {
		selectedMindmap: {
			type: String,
			required: false,
			default: null,
		},
	},
	data() {
		return {
			mindmaps: [],
			loading: true,
		}
	},
	computed: {
		sortedMindmaps() {
			// Sort mindmaps by modification time (newest first)
			return [...this.mindmaps].sort((a, b) => b.mtime - a.mtime)
		},
	},
	mounted() {
		this.loadMindmaps()
	},
	methods: {
		async loadMindmaps() {
			this.loading = true
			
			try {
				const response = await axios.get(
					generateUrl('/apps/notmiro/api/mindmap/list')
				)
				
				if (response.data.status === 'success') {
					this.mindmaps = response.data.files || []
				} else {
					showError(t('notmiro', 'Failed to load mindmaps'))
				}
			} catch (error) {
				console.error('Error loading mindmaps list:', error)
				showError(t('notmiro', 'Failed to load mindmaps'))
			} finally {
				this.loading = false
			}
		},
		
		reloadList() {
			this.loadMindmaps()
		},
		
		selectMindmap(name) {
			this.$emit('select-mindmap', name)
		},
		
		async deleteMindmap(name) {
			// Confirm deletion
			if (!confirm(t('notmiro', 'Are you sure you want to delete "{name}"?', { name: this.formatName(name) }))) {
				return
			}
			
			try {
				const response = await axios.delete(
					generateUrl('/apps/notmiro/api/mindmap/delete'), {
						params: { filename: name }
					}
				)
				
				if (response.data.status === 'success') {
					showSuccess(t('notmiro', 'Mindmap deleted successfully'))
					
					// Remove from list
					this.mindmaps = this.mindmaps.filter(m => m.name !== name)
					
					// If the deleted mindmap was selected, deselect it
					if (this.selectedMindmap === name) {
						this.$emit('select-mindmap', null)
					}
				} else {
					showError(t('notmiro', 'Failed to delete mindmap'))
				}
			} catch (error) {
				console.error('Error deleting mindmap:', error)
				showError(t('notmiro', 'Failed to delete mindmap'))
			}
		},
		
		formatName(name) {
			// Remove extension and convert to readable name
			return name.replace(/\.mindmap$/, '')
				.replace(/-/g, ' ')
				.replace(/_/g, ' ')
		},
		
		formatDate(timestamp) {
			// Format timestamp to readable date
			const date = new Date(timestamp * 1000)
			
			// Use local date formatting
			return date.toLocaleDateString(undefined, {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			})
		},
	},
}
</script>

<style scoped lang="scss">
.mindmap-list {
	width: 100%;
	height: 100%;
	display: flex;
	flex-direction: column;
	border-right: 1px solid var(--color-border);
}

.list-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px;
	border-bottom: 1px solid var(--color-border);
	
	h3 {
		margin: 0;
		font-size: 16px;
	}
}

.empty-state {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 30px;
	color: var(--color-text-maxcontrast);
	text-align: center;
	flex: 1;
}

.mindmap-items {
	list-style: none;
	padding: 0;
	margin: 0;
	overflow-y: auto;
	flex: 1;
}

.mindmap-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 16px;
	border-bottom: 1px solid var(--color-border);
	cursor: pointer;
	
	&:hover {
		background-color: var(--color-background-hover);
	}
	
	&.active {
		background-color: var(--color-primary-light);
		
		.mindmap-name {
			font-weight: bold;
			color: var(--color-primary-text);
		}
	}
}

.mindmap-info {
	flex: 1;
	overflow: hidden;
}

.mindmap-name {
	font-size: 14px;
	margin-bottom: 4px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.mindmap-date {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.mindmap-actions {
	display: flex;
	align-items: center;
	visibility: hidden;
	
	.mindmap-item:hover & {
		visibility: visible;
	}
}

.action-button {
	background: transparent;
	border: none;
	width: 24px;
	height: 24px;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	border-radius: 50%;
	
	&:hover {
		background: var(--color-background-dark);
	}
	
	&.delete:hover {
		background: var(--color-error);
		color: white;
	}
}
</style> 