<template>
	<div class="mindmap-canvas">
		<div v-if="reactFlowInstance" class="reactflow-wrapper" ref="reactFlowWrapper">
			<!-- ReactFlow will be initialized here -->
		</div>
		<div v-else class="loading">
			{{ t('notmiro', 'Loading canvas...') }}
		</div>
	</div>
</template>

<script>
import { nextTick } from 'vue'
import { generateAxios } from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import * as Y from 'yjs'
import { WebsocketProvider } from 'y-websocket'

export default {
	name: 'MindmapCanvas',
	props: {
		mindmapId: {
			type: String,
			required: false,
			default: null,
		},
	},
	data() {
		return {
			reactFlowInstance: null,
			nodes: [],
			edges: [],
			yDoc: null,
			yProvider: null,
			yNodesMap: null,
			yEdgesMap: null,
			userCursors: {}, // Track other users' cursors
			selectedNodeIds: {},  // Track locked nodes by user
		}
	},
	computed: {
		currentUser() {
			return OC.getCurrentUser().uid
		},
	},
	async mounted() {
		await this.initYjs()
		await this.initReactFlow()
		
		if (this.mindmapId) {
			this.loadMindmap(this.mindmapId)
		} else {
			this.createNewMindmap()
		}

		// Set up auto-save interval (every 30 seconds)
		this.autoSaveInterval = setInterval(() => {
			this.saveMindmap()
		}, 30000)
	},
	beforeDestroy() {
		// Clean up resources
		if (this.autoSaveInterval) {
			clearInterval(this.autoSaveInterval)
		}
		
		if (this.yProvider) {
			this.yProvider.destroy()
		}
	},
	methods: {
		async initYjs() {
			// Initialize Yjs document
			this.yDoc = new Y.Doc()
			
			// Initialize shared data structures for collaborative editing
			this.yNodesMap = this.yDoc.getMap('nodes')
			this.yEdgesMap = this.yDoc.getMap('edges')
			this.yUserCursors = this.yDoc.getMap('cursors')
			this.ySelectedNodes = this.yDoc.getMap('selected')
			
			// Set up WebSocket connection
			// In production, you'd use a proper signaling server
			// For development, you can use y-websocket with a local server
			this.yProvider = new WebsocketProvider(
				'wss://demos.yjs.dev', // Consider deploying your own WebSocket server
				'notmiro-' + (this.mindmapId || 'new-mindmap'),
				this.yDoc
			)
			
			// Add current user information
			this.yProvider.awareness.setLocalStateField('user', {
				name: this.currentUser,
				color: this.getRandomColor(),
			})
			
			// Set up awareness change handler for cursor tracking
			this.yProvider.awareness.on('change', this.handleAwarenessChange)
			
			// Subscribe to changes
			this.yNodesMap.observe(this.handleNodesChange)
			this.yEdgesMap.observe(this.handleEdgesChange)
			this.ySelectedNodes.observe(this.handleSelectedNodesChange)
		},
		
		async initReactFlow() {
			// Import ReactFlow dynamically to avoid SSR issues
			const { 
				ReactFlow, 
				Background, 
				Controls, 
				MiniMap,
				useNodesState,
				useEdgesState,
				addEdge,
			} = await import('reactflow')
			
			// Import default styles
			await import('reactflow/dist/style.css')
			
			// Initialize ReactFlow
			// This is simplified - in a real implementation you would use the React bindings
			// Since we're in Vue, we'll need to create custom wrappers or use a library
			
			// For now, we'll just create a placeholder for the react-flow initialization
			// In a real implementation, you would use reactflow properly
			this.reactFlowInstance = {}  // Placeholder for actual ReactFlow instance
			
			// Initialize with empty nodes/edges
			this.nodes = []
			this.edges = []
		},
		
		createNewMindmap() {
			// Create a new empty mindmap with a root node
			const rootNode = {
				id: 'root',
				type: 'default',
				position: { x: 0, y: 0 },
				data: { label: 'New Mindmap' },
			}
			
			// Add the root node to the Yjs map
			this.yNodesMap.set(rootNode.id, rootNode)
		},
		
		async loadMindmap(mindmapId) {
			try {
				const response = await generateAxios().get(
					generateUrl('/apps/notmiro/api/mindmap/load'), {
						params: { filename: mindmapId }
					}
				)
				
				if (response.data.status === 'success') {
					const content = JSON.parse(response.data.content)
					
					// Clear existing data
					this.yNodesMap.clear()
					this.yEdgesMap.clear()
					
					// Load nodes and edges from the file
					if (content.nodes) {
						content.nodes.forEach(node => {
							this.yNodesMap.set(node.id, node)
						})
					}
					
					if (content.edges) {
						content.edges.forEach(edge => {
							this.yEdgesMap.set(edge.id, edge)
						})
					}
				}
			} catch (error) {
				console.error('Failed to load mindmap:', error)
				showError(t('notmiro', 'Failed to load mindmap'))
			}
		},
		
		async saveMindmap() {
			try {
				// Collect nodes and edges from Yjs data
				const nodes = Array.from(this.yNodesMap.values())
				const edges = Array.from(this.yEdgesMap.values())
				
				// Create the content to save
				const content = JSON.stringify({
					nodes,
					edges,
					metadata: {
						lastModified: new Date().toISOString(),
						version: '1.0',
					}
				})
				
				// Save to server
				const filename = this.mindmapId || 'untitled-mindmap'
				await generateAxios().post(
					generateUrl('/apps/notmiro/api/mindmap/save'), {
						filename,
						content,
					}
				)
				
				// If this was a new mindmap, update the ID
				if (!this.mindmapId) {
					this.$emit('mindmap-created', filename)
					this.mindmapId = filename
				}
				
				showSuccess(t('notmiro', 'Mindmap saved successfully'))
			} catch (error) {
				console.error('Failed to save mindmap:', error)
				showError(t('notmiro', 'Failed to save mindmap'))
			}
		},
		
		// Collaborative editing handlers
		handleNodesChange(event) {
			// Update local nodes based on Yjs changes
			this.nodes = Array.from(this.yNodesMap.values())
			
			// Update the ReactFlow instance
			if (this.reactFlowInstance) {
				// In a real implementation, you would update the ReactFlow nodes
				console.log('Nodes updated:', this.nodes)
			}
		},
		
		handleEdgesChange(event) {
			// Update local edges based on Yjs changes
			this.edges = Array.from(this.yEdgesMap.values())
			
			// Update the ReactFlow instance
			if (this.reactFlowInstance) {
				// In a real implementation, you would update the ReactFlow edges
				console.log('Edges updated:', this.edges)
			}
		},
		
		handleSelectedNodesChange(event) {
			// Update locked nodes
			this.selectedNodeIds = Object.fromEntries(
				Array.from(this.ySelectedNodes.entries())
			)
			
			// In a real implementation, you would update the UI to show locked nodes
			console.log('Selected nodes changed:', this.selectedNodeIds)
		},
		
		handleAwarenessChange() {
			// Update cursor positions based on awareness
			const states = this.yProvider.awareness.getStates()
			
			this.userCursors = {}
			states.forEach((state, clientId) => {
				if (state.cursor && state.user && clientId !== this.yProvider.awareness.clientID) {
					this.userCursors[clientId] = {
						position: state.cursor,
						user: state.user,
					}
				}
			})
			
			// In a real implementation, you would update cursor UI elements
			console.log('User cursors updated:', this.userCursors)
		},
		
		// Helper to generate a random color for user identification
		getRandomColor() {
			const colors = [
				'#ff0000', '#00ff00', '#0000ff', '#ffff00',
				'#ff00ff', '#00ffff', '#ff8000', '#8000ff',
			]
			return colors[Math.floor(Math.random() * colors.length)]
		},
		
		// Node/edge manipulation methods
		addNode(type = 'default', position = { x: 0, y: 0 }, data = { label: 'New Node' }) {
			const newNodeId = `node-${Date.now()}`
			const newNode = {
				id: newNodeId,
				type,
				position,
				data,
			}
			
			// Add to Yjs shared data
			this.yNodesMap.set(newNodeId, newNode)
			return newNodeId
		},
		
		updateNode(nodeId, updates) {
			const node = this.yNodesMap.get(nodeId)
			if (node) {
				// Check if node is locked by another user
				const lockedBy = this.ySelectedNodes.get(nodeId)
				if (lockedBy && lockedBy !== this.currentUser) {
					showError(t('notmiro', 'This node is being edited by {user}', { user: lockedBy }))
					return false
				}
				
				// Update the node
				this.yNodesMap.set(nodeId, { ...node, ...updates })
				return true
			}
			return false
		},
		
		deleteNode(nodeId) {
			// Check if node is locked by another user
			const lockedBy = this.ySelectedNodes.get(nodeId)
			if (lockedBy && lockedBy !== this.currentUser) {
				showError(t('notmiro', 'This node is being edited by {user}', { user: lockedBy }))
				return false
			}
			
			// Delete the node
			this.yNodesMap.delete(nodeId)
			
			// Delete related edges
			this.edges.forEach(edge => {
				if (edge.source === nodeId || edge.target === nodeId) {
					this.yEdgesMap.delete(edge.id)
				}
			})
			
			return true
		},
		
		addEdge(source, target) {
			const newEdgeId = `edge-${Date.now()}`
			const newEdge = {
				id: newEdgeId,
				source,
				target,
			}
			
			// Add to Yjs shared data
			this.yEdgesMap.set(newEdgeId, newEdge)
			return newEdgeId
		},
		
		deleteEdge(edgeId) {
			this.yEdgesMap.delete(edgeId)
		},
		
		// Lock/unlock nodes for collaborative editing
		selectNode(nodeId) {
			// Claim this node for editing
			this.ySelectedNodes.set(nodeId, this.currentUser)
		},
		
		deselectNode(nodeId) {
			// Only remove if we own the lock
			if (this.ySelectedNodes.get(nodeId) === this.currentUser) {
				this.ySelectedNodes.delete(nodeId)
			}
		},
		
		// Update cursor position
		updateCursorPosition(x, y) {
			this.yProvider.awareness.setLocalStateField('cursor', { x, y })
		},
	},
}
</script>

<style scoped lang="scss">
.mindmap-canvas {
	width: 100%;
	height: 100%;
	position: relative;
}

.reactflow-wrapper {
	width: 100%;
	height: 100%;
}

.loading {
	display: flex;
	justify-content: center;
	align-items: center;
	height: 100%;
	font-size: 18px;
	color: var(--color-text-maxcontrast);
}

/* Cursor styles for other users */
.user-cursor {
	position: absolute;
	pointer-events: none;
	z-index: 1000;
	
	.cursor-pointer {
		width: 10px;
		height: 10px;
		border-radius: 50%;
	}
	
	.cursor-label {
		background: var(--color-primary);
		color: var(--color-primary-text);
		padding: 2px 6px;
		border-radius: 4px;
		font-size: 12px;
		transform: translateY(-100%);
		white-space: nowrap;
	}
}

/* Node locked appearance */
.node-locked {
	opacity: 0.7;
	pointer-events: none;
	
	&::after {
		content: '🔒';
		position: absolute;
		top: -10px;
		right: -10px;
		font-size: 14px;
	}
}
</style> 