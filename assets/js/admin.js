document.addEventListener('DOMContentLoaded', function() {
    let currentView = 'grid';
    
    initTagFilter();
    initLazyLoading();
    
    // ===== 侧边栏切换 =====
    window.toggleSidebar = function() {
        var sidebar = document.getElementById('adminSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (sidebar && overlay) {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
    };

    function toggleView(view) {
        currentView = view;
        const gridView = document.getElementById('imageGridView');
        const listView = document.getElementById('imageListView');
        
        if (view === 'grid') {
            gridView.style.display = 'flex';
            listView.style.display = 'none';
        } else {
            gridView.style.display = 'none';
            listView.style.display = 'block';
        }
        
        updateViewButtons(view);
        observeImages();
    }
    
    function updateViewButtons(activeView) {
        const buttons = document.querySelectorAll('.btn-group .btn');
        buttons.forEach(btn => {
            btn.classList.remove('active', 'btn-primary');
            btn.classList.add('btn-outline-primary');
            if (btn.textContent.includes(activeView === 'grid' ? '网格' : '列表')) {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('active', 'btn-primary');
            }
        });
    }
    
    function copyUrlToClipboard(e) {
        const url = e.target.getAttribute('data-url') || 
                    e.target.closest('.copy-url').getAttribute('data-url');
        
        if (!url) return;
        
        const target = e.target.classList.contains('copy-url') 
            ? e.target 
            : e.target.closest('.copy-url');
        
        navigator.clipboard.writeText(url).then(function() {
            const originalHTML = target.innerHTML;
            
            target.innerHTML = '<i class="fas fa-check"></i>';
            target.classList.remove('btn-outline-primary');
            target.classList.add('btn-success');
            
            setTimeout(() => {
                target.innerHTML = originalHTML;
                target.classList.remove('btn-success');
                target.classList.add('btn-outline-primary');
            }, 2000);
        }).catch(function() {
            showAlert('复制失败，请手动复制链接');
        });
    }
    
    function showAlert(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning alert-dismissible fade show';
        alertDiv.textContent = message;
        
        const btnClose = document.createElement('button');
        btnClose.type = 'button';
        btnClose.className = 'btn-close';
        btnClose.setAttribute('data-bs-dismiss', 'alert');
        alertDiv.appendChild(btnClose);
        
        const main = document.querySelector('.admin-main');
        if (main && main.firstChild) {
            main.insertBefore(alertDiv, main.firstChild);
        }
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 3000);
    }
    
    function clearAllImages() {
        if (confirm('确定要清空所有图片吗？此操作不可恢复！')) {
            if (confirm('再次确认：这将删除所有图片数据，包括本地文件和 GitHub 存储的图片。')) {
                fetch('admin_actions.php?action=clear_all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('所有图片已清空');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('清空失败：' + data.message);
                    }
                })
                .catch(error => {
                    showAlert('操作失败：' + error);
                });
            }
        }
    }
    
    function showImageModal(imageUrl, imageName) {
        const existingModal = document.getElementById('imageModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        const modalHTML = `
            <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imageModalLabel">${escapeHtml(imageName)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center p-4">
                            <img src="${escapeHtml(imageUrl)}" class="img-fluid" alt="${escapeHtml(imageName)}" loading="eager">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                            <button type="button" class="btn btn-primary" onclick="copyUrlToClipboardModal('${escapeHtml(imageUrl)}')">
                                <i class="fas fa-clipboard me-1"></i>复制链接
                            </button>
                            <a href="${escapeHtml(imageUrl)}" class="btn btn-info" download="${escapeHtml(imageName)}" target="_blank">
                                <i class="fas fa-download me-1"></i>下载
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        modal.show();
        
        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }
    
    function copyUrlToClipboardModal(url) {
        navigator.clipboard.writeText(url).then(function() {
            const btn = document.querySelector('#imageModal .btn-primary');
            if (btn) {
                const originalText = btn.textContent;
                btn.textContent = '已复制';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
                
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-primary');
                }, 2000);
            }
        });
    }
    
    function initTagFilter() {
        const tagButtons = document.querySelectorAll('.btn-group a[href*="tag="]');
        tagButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');
                
                const cardBody = document.querySelector('.card-body');
                const originalContent = cardBody.innerHTML;
                cardBody.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <p class="text-muted">正在筛选图片...</p>
                    </div>
                `;
                
                setTimeout(() => {
                    window.location.href = url;
                }, 300);
            });
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const currentUrl = new URL(window.location.href);
                if (currentUrl.searchParams.has('tag')) {
                    window.location.href = '?tag=all';
                }
            }
        });
    }
    
    function initLazyLoading() {
        const images = document.querySelectorAll('img[data-src], img[src]:not([src=""])');
        
        images.forEach(img => {
            if (!img.complete) {
                img.parentElement.style.position = 'relative';
                img.parentElement.style.overflow = 'hidden';
                
                const skeleton = document.createElement('div');
                skeleton.className = 'image-skeleton';
                skeleton.style.cssText = `
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                    background-size: 200% 100%;
                    animation: shimmer 1.5s infinite;
                    border-radius: 8px;
                    z-index: 1;
                `;
                
                img.style.position = 'relative';
                img.style.zIndex = '2';
                img.parentElement.insertBefore(skeleton, img);
                
                img.addEventListener('load', function() {
                    if (skeleton && skeleton.parentNode) {
                        skeleton.remove();
                    }
                });
            }
        });
        
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shimmer {
                0% { background-position: -200% 0; }
                100% { background-position: 200% 0; }
            }
        `;
        document.head.appendChild(style);
        
        observeImages();
    }
    
    function observeImages() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    
                    observer.unobserve(img);
                });
            }, { rootMargin: '50px 0px' });
            
            document.querySelectorAll('img').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('copy-url') || 
            e.target.closest('.copy-url')) {
            copyUrlToClipboard(e);
        }
        
        if (e.target.classList.contains('bi-eye') || 
            e.target.closest('.bi-eye')) {
            e.preventDefault();
            const link = e.target.closest('a');
            if (link) {
                const imageUrl = link.href;
                const cardBody = link.closest('.card') || link.closest('tr');
                const imageName = cardBody?.querySelector('.card-title')?.textContent || 
                                cardBody?.querySelector('td:nth-child(2)')?.textContent || 
                                '图片';
                showImageModal(imageUrl, imageName);
            }
        }
    });
    
    // ===== 标签管理 =====
    function getTagsFromContainer(container) {
        const badges = container.querySelectorAll('.tag-badge');
        return Array.from(badges).map(badge => {
            return badge.childNodes[0].textContent.trim();
        }).filter(t => t);
    }

    function updateImageTags(imageId, tags, container) {
        const formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('tags', tags.join(','));

        return fetch('admin_actions.php?action=update_tags', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderTags(container, imageId, data.tags);
                return true;
            } else {
                showAlert('标签更新失败：' + data.message);
                return false;
            }
        })
        .catch(error => {
            showAlert('标签更新失败：' + error);
            return false;
        });
    }

    function renderTags(container, imageId, tags) {
        const storageBadge = container.querySelector('[class*="bg-success"], [class*="bg-info"], [class*="bg-danger"], [class*="bg-warning"]:not(.tag-add-btn)');
        const addBtn = container.querySelector('.tag-add-btn');

        const existingBadges = container.querySelectorAll('.tag-badge');
        existingBadges.forEach(b => b.remove());

        const frag = document.createDocumentFragment();
        tags.forEach(tag => {
            const span = document.createElement('span');
            span.className = 'badge bg-secondary me-1 tag-badge';
            span.style.fontSize = '0.8rem';
            span.style.paddingRight = '0.35em';
            span.textContent = tag;

            const removeSpan = document.createElement('span');
            removeSpan.className = 'tag-remove-btn';
            removeSpan.style.cssText = 'margin-left:4px; opacity:0.7; cursor:pointer; font-size:0.8em;';
            removeSpan.textContent = '×';
            span.appendChild(removeSpan);

            frag.appendChild(span);
        });

        if (storageBadge) {
            storageBadge.parentNode.insertBefore(frag, storageBadge.nextSibling);
        } else if (addBtn) {
            container.insertBefore(frag, addBtn);
        } else {
            container.insertBefore(frag, container.firstChild);
        }
    }

    function getAllExistingTags() {
        const allTags = new Set();
        document.querySelectorAll('.tag-container').forEach(function(c) {
            c.querySelectorAll('.tag-badge').forEach(function(b) {
                const tag = b.childNodes[0].textContent.trim();
                if (tag) allTags.add(tag);
            });
        });
        return Array.from(allTags).sort();
    }

    function showTagInput(container, imageId) {
        if (container.querySelector('.tag-input-wrapper')) return;

        const allExistingTags = getAllExistingTags();
        const currentTags = getTagsFromContainer(container);

        const wrapper = document.createElement('span');
        wrapper.className = 'tag-input-wrapper';
        wrapper.style.cssText = 'display:inline-flex; flex-direction:column; position:relative;';

        const inputRow = document.createElement('span');
        inputRow.style.cssText = 'display:inline-flex; align-items:center;';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm tag-input';
        input.style.cssText = 'width:80px; height:22px; padding:0 4px; font-size:0.7rem;';
        input.placeholder = '标签';

        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'btn btn-success btn-sm tag-input-confirm';
        confirmBtn.style.cssText = 'height:22px; padding:0 6px; font-size:0.7rem; margin-left:2px;';
        confirmBtn.innerHTML = '✓';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-secondary btn-sm tag-input-cancel';
        cancelBtn.style.cssText = 'height:22px; padding:0 6px; font-size:0.7rem; margin-left:1px;';
        cancelBtn.innerHTML = '✕';

        inputRow.appendChild(input);
        inputRow.appendChild(confirmBtn);
        inputRow.appendChild(cancelBtn);
        wrapper.appendChild(inputRow);

        let dropdown = null;

        function showDropdown() {
            if (dropdown) dropdown.remove();
            dropdown = null;

            const val = input.value.trim().toLowerCase();
            let tags = allExistingTags.filter(function(t) {
                return !currentTags.includes(t) && (!val || t.toLowerCase().includes(val));
            });

            if (tags.length === 0) return;

            dropdown = document.createElement('div');
            dropdown.className = 'tag-dropdown';
            dropdown.style.cssText = 'position:absolute; top:100%; left:0; z-index:1000; background:#fff; border:1px solid #dee2e6; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.1); max-height:150px; overflow-y:auto; min-width:80px;';

            tags.forEach(function(tag) {
                const item = document.createElement('div');
                item.className = 'tag-dropdown-item';
                item.style.cssText = 'padding:4px 8px; font-size:0.7rem; cursor:pointer;';
                item.textContent = tag;
                item.addEventListener('mouseenter', function() {
                    item.style.backgroundColor = '#e9ecef';
                });
                item.addEventListener('mouseleave', function() {
                    item.style.backgroundColor = '';
                });
                item.addEventListener('click', function() {
                    input.value = tag;
                    if (dropdown) { dropdown.remove(); dropdown = null; }
                    confirm();
                });
                dropdown.appendChild(item);
            });

            wrapper.appendChild(dropdown);
        }

        function hideDropdown() {
            if (dropdown) { dropdown.remove(); dropdown = null; }
        }

        const addBtn = container.querySelector('.tag-add-btn');
        container.insertBefore(wrapper, addBtn);

        function cleanup() {
            hideDropdown();
            wrapper.remove();
        }

        function confirm() {
            const val = input.value.trim();
            if (!val) {
                cleanup();
                return;
            }
            const curTags = getTagsFromContainer(container);
            if (curTags.includes(val)) {
                cleanup();
                return;
            }
            curTags.push(val);
            updateImageTags(imageId, curTags, container).then(() => cleanup());
        }

        confirmBtn.addEventListener('click', confirm);
        cancelBtn.addEventListener('click', cleanup);
        input.addEventListener('input', showDropdown);
        input.addEventListener('focus', showDropdown);
        input.addEventListener('blur', function() {
            setTimeout(hideDropdown, 150);
        });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); confirm(); }
            if (e.key === 'Escape') { e.preventDefault(); cleanup(); }
        });

        input.focus();
        showDropdown();
    }

    document.addEventListener('click', function(e) {
        const container = e.target.closest('.tag-container');
        if (!container) return;

        const removeBtn = e.target.closest('.tag-remove-btn');
        if (removeBtn) {
            e.stopPropagation();
            const badge = removeBtn.closest('.tag-badge');
            const imageId = container.dataset.imageId;
            const tagToRemove = badge.childNodes[0].textContent.trim();

            badge.style.opacity = '0.3';
            const currentTags = getTagsFromContainer(container).filter(t => t !== tagToRemove);
            updateImageTags(imageId, currentTags, container);
            return;
        }

        const addBtn = e.target.closest('.tag-add-btn');
        if (addBtn) {
            e.stopPropagation();
            const imageId = container.dataset.imageId;
            showTagInput(container, imageId);
        }
    });

    window.toggleView = toggleView;
    window.clearAllImages = clearAllImages;
    window.copyUrlToClipboardModal = copyUrlToClipboardModal;

    window.deleteTag = function(tagName) {
        if (!confirm('确定要删除标签「' + tagName + '」吗？\n\n此操作将从所有图片中移除该标签，不可恢复！')) {
            return;
        }

        var fd = new FormData();
        fd.append('tag', tagName);

        fetch('admin_actions.php?action=delete_tag', {
            method: 'POST',
            body: fd
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message);
                setTimeout(() => { window.location.href = '?tag=all'; }, 1000);
            } else {
                showAlert('删除失败：' + data.message);
            }
        })
        .catch(error => {
            showAlert('操作失败：' + error);
        });
    };

    window.renameTag = function(oldTagName) {
        var newTagName = prompt('将标签「' + oldTagName + '」重命名为：', oldTagName);
        if (!newTagName || newTagName.trim() === '') {
            return;
        }
        newTagName = newTagName.trim();
        if (newTagName === oldTagName) {
            return;
        }

        var fd = new FormData();
        fd.append('old_tag', oldTagName);
        fd.append('new_tag', newTagName);

        fetch('admin_actions.php?action=rename_tag', {
            method: 'POST',
            body: fd
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message);
                setTimeout(() => { window.location.href = '?tag=' + encodeURIComponent(newTagName); }, 1000);
            } else {
                showAlert('重命名失败：' + data.message);
            }
        })
        .catch(error => {
            showAlert('操作失败：' + error);
        });
    };

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'logout') {
        fetch('admin_actions.php?action=logout', {method: 'POST'})
            .then(() => {
                window.location.href = 'admin_login.php';
            });
    }

    // ===== 批量上传 =====
    let batchFiles = [];
    let batchUploading = false;

    const batchFileInput = document.getElementById('batchFileInput');
    const batchUploadBtn = document.getElementById('batchUploadBtn');
    const batchFileLabel = document.getElementById('batchFileLabel');

    if (batchFileInput) {
        batchFileInput.addEventListener('change', function() {
            handleBatchFiles(this.files);
        });
    }

    function handleBatchFiles(files) {
        if (batchUploading) return;
        batchFiles = Array.from(files).filter(function(f) { return /^image\//.test(f.type); });
        batchUploadBtn.disabled = batchFiles.length === 0;
        if (batchFiles.length > 0) {
            batchFileLabel.textContent = '已选 ' + batchFiles.length + ' 张';
            batchUploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> 上传';
        } else {
            batchFileLabel.textContent = '选择图片';
        }
    }

    window.startBatchUpload = function() {
        if (batchUploading || batchFiles.length === 0) return;
        batchUploading = true;
        batchUploadBtn.disabled = true;

        var tags = document.getElementById('batchTags').value;
        var progressDiv = document.getElementById('batchProgress');
        var progressBar = document.getElementById('batchProgressBar');
        var batchStatus = document.getElementById('batchStatus');
        var batchCount = document.getElementById('batchCount');
        var batchResults = document.getElementById('batchResults');

        progressDiv.style.display = 'block';
        batchResults.innerHTML = '';
        batchStatus.textContent = '上传中...';

        var completed = 0;
        var successCount = 0;
        var failCount = 0;
        var total = batchFiles.length;

        function uploadNext(index) {
            if (index >= total) {
                batchStatus.textContent = '上传完成：成功 ' + successCount + ' 张' + (failCount > 0 ? '，失败 ' + failCount + ' 张' : '');
                batchUploadBtn.innerHTML = '<i class="fas fa-check me-1"></i> 完成';
                batchUploading = false;
                batchFiles = [];
                batchFileLabel.textContent = '选择图片';
                setTimeout(function() { location.reload(); }, 2000);
                return;
            }

            var file = batchFiles[index];
            var fd = new FormData();
            fd.append('image', file);
            fd.append('tags', tags);

            var xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var filePct = (e.loaded / e.total) * 0.9;
                    var overallPct = ((index + filePct) / total) * 100;
                    progressBar.style.width = overallPct + '%';
                    batchCount.textContent = (index + 1) + ' / ' + total;
                }
            });

            xhr.addEventListener('load', function() {
                completed++;
                var progressPct = (completed / total) * 100;
                progressBar.style.width = progressPct + '%';
                batchCount.textContent = completed + ' / ' + total;

                try {
                    var resp = JSON.parse(xhr.responseText);
                    var div = document.createElement('div');
                    div.className = 'batch-result-item ' + (resp.success ? 'success' : 'error');
                    if (resp.success) {
                        successCount++;
                        div.textContent = '\u2713 ' + file.name;
                    } else {
                        failCount++;
                        div.textContent = '\u2717 ' + file.name + ' \u2014 ' + resp.message;
                    }
                    batchResults.appendChild(div);
                    batchResults.scrollTop = batchResults.scrollHeight;
                } catch (e) {
                    failCount++;
                    var div = document.createElement('div');
                    div.className = 'batch-result-item error';
                    div.textContent = '\u2717 ' + file.name + ' \u2014 \u670d\u52a1\u5668\u54cd\u5e94\u9519\u8bef';
                    batchResults.appendChild(div);
                }

                uploadNext(index + 1);
            });

            xhr.addEventListener('error', function() {
                completed++;
                failCount++;
                var progressPct = (completed / total) * 100;
                progressBar.style.width = progressPct + '%';
                batchCount.textContent = completed + ' / ' + total;
                var div = document.createElement('div');
                div.className = 'batch-result-item error';
                div.textContent = '\u2717 ' + file.name + ' \u2014 \u7f51\u7edc\u9519\u8bef';
                batchResults.appendChild(div);
                uploadNext(index + 1);
            });

            xhr.open('POST', 'upload.php');
            xhr.send(fd);
        }

        uploadNext(0);
    };
});
