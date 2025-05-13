<template>
	<NcAppContent>
		<div id="notmiro" class="notmiro-container">
			<div class="notmiro-sidebar" :class="{ 'collapsed': sidebarCollapsed }">
				<div class="sidebar-header">
					<h2>{{ t('notmiro', 'NotMiro') }}</h2>
					<NcButton @click="toggleSidebar" type="tertiary" class="collapse-button">
						{{ sidebarCollapsed ? '»' : '«' }}
					</NcButton>
				</div>
				
				<div v-if="!sidebarCollapsed" class="sidebar-content">
					<MindmapList 
						:selected-mindmap="currentMindmapId" 
						@select-mindmap="loadMindmap"
					/>
				</div>
			</div>
			
			<div class="notmiro-content">
				<div class="notmiro-toolbar">
					<div class="toolbar-group">
						<NcButton @click="showNewMindmapDialog" class="primary">
							{{ t('notmiro', 'New Mindmap') }}
						</NcButton>
						<NcButton @click="saveCurrentMindmap" :disabled="!currentMindmapId">
							{{ t('notmiro', 'Save') }}
						</NcButton>
					</div>
					
					<div v-if="currentMindmapId" class="toolbar-group mindmap-info">
						<span class="mindmap-name">{{ formatMindmapName(currentMindmapId) }}</span>
						<span v-if="isSaving" class="saving-indicator">{{ t('notmiro', 'Saving...') }}</span>
						<span v-else-if="lastSaved" class="saving-indicator">
							{{ t('notmiro', 'Last saved: {time}', { time: formatTime(lastSaved) }) }}
						</span>
					</div>
					
					<div class="toolbar-group users-online">
						<span v-if="connectedUsers.length > 0">
							{{ t('notmiro', '{count} user(s) online', { count: connectedUsers.length }) }}
						</span>
						<div class="user-avatars">
							<div 
								v-for="user in connectedUsers" 
								:key="user.clientId"
								class="user-avatar"
								:style="{ backgroundColor: user.color }"
								:title="user.name"
							>
								{{ user.initials }}
							</div>
						</div>
					</div>
				</div>
				
				<div class="canvas-container">
					<div v-if="!currentMindmapId && !isCreatingNew" class="empty-state">
						<div class="empty-state-content">
							<h3>{{ t('notmiro', 'Welcome to NotMiro') }}</h3>
							<p>{{ t('notmiro', 'Create a new mindmap or select one from the sidebar to get started.') }}</p>
							<NcButton @click="showNewMindmapDialog" class="primary">
								{{ t('notmiro', 'Create New Mindmap') }}
							</NcButton>
						</div>
					</div>
					
					<MindmapCanvas
						v-else
						ref="canvas"
						:mindmap-id="currentMindmapId"
						@mindmap-created="handleMindmapCreated"
						@users-changed="handleUsersChanged"
					/>
				</div>
			</div>
			
			<!-- New Mindmap Dialog -->
			<NcDialog
				v-if="showDialog"
				:title="t('notmiro', 'Create New Mindmap')"
				@close="closeDialog"
			>
				<div class="dialog-content">
					<NcTextField
						:label="t('notmiro', 'Mindmap Name')"
						v-model="newMindmapName"
						:placeholder="t('notmiro', 'My Awesome Mindmap')"
						:error="dialogError"
					/>
				</div>
				<template #actions>
					<NcButton @click="closeDialog">
						{{ t('notmiro', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" @click="createNewMindmap" :disabled="!newMindmapName">
						{{ t('notmiro', 'Create') }}
					</NcButton>
				</template>
			</NcDialog>
		</div>
	</NcAppContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import MindmapCanvas from './components/MindmapCanvas.vue'
import MindmapList from './components/MindmapList.vue'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'App',
	components: {
		NcAppContent,
		NcButton,
		NcDialog,
		NcTextField,
		MindmapCanvas,
		MindmapList,
	},
	data() {
		return {
			currentMindmapId: null,
			isCreatingNew: false,
			isSaving: false,
			lastSaved: null,
			sidebarCollapsed: false,
			showDialog: false,
			newMindmapName: '',
			dialogError: null,
			connectedUsers: [],
		}
	},
	methods: {
		toggleSidebar() {
			this.sidebarCollapsed = !this.sidebarCollapsed
		},
		
		showNewMindmapDialog() {
			this.showDialog = true
			this.newMindmapName = ''
			this.dialogError = null
		},
		
		closeDialog() {
			this.showDialog = false
		},
		
		createNewMindmap() {
			if (!this.newMindmapName) {
				this.dialogError = t('notmiro', 'Please enter a name for your mindmap')
				return
			}
			
			// Format the name (replace spaces with hyphens, etc.)
			const formattedName = this.newMindmapName
				.trim()
				.toLowerCase()
				.replace(/\s+/g, '-')
				.replace(/[^a-z0-9-]/g, '')
			
			if (formattedName.length === 0) {
				this.dialogError = t('notmiro', 'Invalid name. Please use only letters, numbers, and hyphens.')
				return
			}
			
			// Close the dialog
			this.closeDialog()
			
			// Mark as creating new
			this.isCreatingNew = true
			this.currentMindmapId = null
			
			// Initialize a new mindmap
			this.$nextTick(() => {
				if (this.$refs.canvas) {
					// Pass the name to the canvas component
					this.$refs.canvas.createNewMindmap(formattedName)
				}
			})
		},
		
		loadMindmap(mindmapId) {
			this.currentMindmapId = mindmapId
			this.isCreatingNew = false
			
			// Reset saving status
			this.isSaving = false
			this.lastSaved = null
		},
		
		saveCurrentMindmap() {
			if (!this.currentMindmapId && !this.isCreatingNew) {
				return
			}
			
			this.isSaving = true
			
			if (this.$refs.canvas) {
				this.$refs.canvas.saveMindmap()
					.then(() => {
						this.lastSaved = new Date()
						this.isSaving = false
					})
					.catch(() => {
						this.isSaving = false
					})
			}
		},
		
		handleMindmapCreated(mindmapId) {
			this.currentMindmapId = mindmapId
			this.isCreatingNew = false
			this.lastSaved = new Date()
			
			// Refresh the mindmap list
			if (this.$refs.mindmapList) {
				this.$refs.mindmapList.reloadList()
			}
			
			showSuccess(t('notmiro', 'Mindmap created successfully'))
		},
		
		handleUsersChanged(users) {
			this.connectedUsers = users.map(user => ({
				...user,
				initials: user.name.slice(0, 2).toUpperCase(),
			}))
		},
		
		formatMindmapName(name) {
			if (!name) return ''
			
			// Remove extension and convert hyphens to spaces
			return name.replace(/\.mindmap$/, '')
				.replace(/-/g, ' ')
				.replace(/_/g, ' ')
				// Capitalize first letter of each word
				.replace(/\b\w/g, l => l.toUpperCase())
		},
		
		formatTime(date) {
			if (!date) return ''
			
			// Format date to time, e.g. "10:25 AM"
			return date.toLocaleTimeString(undefined, {
				hour: '2-digit',
				minute: '2-digit',
			})
		},
	},
	mounted() {
		// Set up auto-save interval
		this.autoSaveInterval = setInterval(() => {
			if (this.currentMindmapId || this.isCreatingNew) {
				this.saveCurrentMindmap()
			}
		}, 60000) // Auto-save every minute
	},
	beforeDestroy() {
		if (this.autoSaveInterval) {
			clearInterval(this.autoSaveInterval)
		}
	},
}
</script>

<style scoped lang="scss">
.notmiro-container {
	display: flex;
	height: 100%;
	width: 100%;
	color: var(--color-text-primary);
}

.notmiro-sidebar {
	width: 300px;
	border-right: 1px solid var(--color-border);
	display: flex;
	flex-direction: column;
	transition: width 0.3s ease;
	
	&.collapsed {
		width: 40px;
	}
}

.sidebar-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px;
	height: 50px;
	border-bottom: 1px solid var(--color-border);
	
	h2 {
		margin: 0;
		font-size: 16px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.sidebar-content {
	flex: 1;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.collapse-button {
	height: 30px;
	width: 30px;
	min-width: 30px;
	padding: 0;
}

.notmiro-content {
	flex: 1;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

.notmiro-toolbar {
	display: flex;
	padding: 8px;
	border-bottom: 1px solid var(--color-border);
	gap: 8px;
	justify-content: space-between;
	height: 50px;
}

.toolbar-group {
	display: flex;
	align-items: center;
	gap: 8px;
}

.mindmap-info {
	.mindmap-name {
		font-weight: bold;
		margin-right: 8px;
	}
	
	.saving-indicator {
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}
}

.users-online {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.user-avatars {
	display: flex;
	margin-left: 5px;
}

.user-avatar {
	width: 24px;
	height: 24px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: white;
	font-size: 10px;
	font-weight: bold;
	margin-left: -5px;
	border: 2px solid var(--color-main-background);
}

.canvas-container {
	flex: 1;
	overflow: hidden;
	position: relative;
}

.empty-state {
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	
	.empty-state-content {
		text-align: center;
		max-width: 400px;
		
		h3 {
			margin-bottom: 10px;
		}
		
		p {
			margin-bottom: 20px;
			color: var(--color-text-maxcontrast);
		}
	}
}

.dialog-content {
	padding: 20px;
	min-width: 300px;
}
</style>
