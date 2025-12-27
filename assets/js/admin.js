document.addEventListener('DOMContentLoaded', function() {
    let currentView = 'grid';
    
    // 初始化标签筛选功能
    initTagFilter();
    
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
        
        navigator.clipboard.writeText(url).then(function() {
            const target = e.target.classList.contains('copy-url') ? e.target : e.target.closest('.copy-url');
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
            alert('复制失败，请手动复制链接: ' + url);
        });
    }
    
    function clearAllImages() {
        if (confirm('确定要清空所有图片吗？此操作不可恢复！')) {
            if (confirm('再次确认：这将删除所有图片数据，包括本地文件和GitHub存储的图片。')) {
                fetch('admin_actions.php?action=clear_all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('所有图片已清空');
                        location.reload();
                    } else {
                        alert('清空失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('操作失败: ' + error);
                });
            }
        }
    }
    
    function showImageModal(imageUrl, imageName) {
        const modalHTML = `
            <div class="modal fade" id="imageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${imageName}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageUrl}" class="img-fluid" alt="${imageName}">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                            <button type="button" class="btn btn-primary" onclick="copyUrlToClipboardModal('${imageUrl}')">复制链接</button>
                            <a href="${imageUrl}" class="btn btn-info" download="${imageName}" target="_blank">下载</a>
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
            const originalText = btn.textContent;
            btn.textContent = '已复制';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 2000);
        });
    }
    
    function initTagFilter() {
        // 为标签按钮添加点击事件
        const tagButtons = document.querySelectorAll('.btn-group a[href*="tag="]');
        tagButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');
                
                // 显示加载状态
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
                
                // 跳转到筛选页面
                setTimeout(() => {
                    window.location.href = url;
                }, 300);
            });
        });
        
        // 添加键盘快捷键支持
        document.addEventListener('keydown', function(e) {
            // Escape 键清除筛选
            if (e.key === 'Escape') {
                const currentUrl = new URL(window.location.href);
                if (currentUrl.searchParams.has('tag')) {
                    window.location.href = '?tag=all';
                }
            }
        });
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
                const imageName = link.closest('.card').querySelector('.card-title')?.textContent || 
                                link.closest('tr').querySelector('td:nth-child(2)')?.textContent || 
                                '图片';
                showImageModal(imageUrl, imageName);
            }
        }
    });
    
    window.toggleView = toggleView;
    window.clearAllImages = clearAllImages;
    window.copyUrlToClipboardModal = copyUrlToClipboardModal;
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'logout') {
        fetch('admin_actions.php?action=logout', {method: 'POST'})
            .then(() => {
                window.location.href = 'admin_login.php';
            });
    }
});