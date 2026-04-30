document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = uploadProgress.querySelector('.progress-bar');
    const progressPercent = document.getElementById('progressPercent');
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('image');
    
    let uploadCard = document.getElementById('uploadCard');
    let uploadResult = document.getElementById('uploadResult');
    
    initDragAndDrop();
    
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const tags = document.getElementById('tags').value;
        
        if (!fileInput.files.length) {
            showAlert('请选择要上传的图片', 'warning');
            return;
        }
        
        uploadFile(fileInput.files[0], tags);
    });
    
    function uploadFile(file, tags) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('tags', tags);
        
        uploadProgress.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const roundedPercent = Math.round(percentComplete);
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = roundedPercent + '%';
                if (progressPercent) {
                    progressPercent.textContent = roundedPercent + '%';
                }
            }
        });
        
        xhr.addEventListener('load', function() {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    showAlert('图片上传成功！链接已自动复制到剪贴板', 'success');
                    uploadForm.reset();
                    
                    copyToClipboard(response.url)
                        .then(() => {
                            addImageToGallery({
                                url: response.url,
                                tags: tags,
                                upload_time: new Date().toISOString()
                            });
                        })
                        .catch(() => {
                            addImageToGallery({
                                url: response.url,
                                tags: tags,
                                upload_time: new Date().toISOString()
                            });
                            showAlert('图片上传成功！但链接复制失败，请手动复制', 'warning');
                        });
                } else {
                    showAlert('上传失败：' + response.message, 'danger');
                }
            } catch (error) {
                showAlert('上传失败：服务器响应错误', 'danger');
            }
            
            uploadProgress.style.display = 'none';
        });
        
        xhr.addEventListener('error', function() {
            showAlert('上传失败：网络错误', 'danger');
            uploadProgress.style.display = 'none';
        });
        
        xhr.open('POST', 'upload.php');
        xhr.send(formData);
    }
    
    function addImageToGallery(image) {
        const container = document.getElementById('uploadedImageContainer');
        if (!container) return;
        
        const tagsArray = image.tags && image.tags.trim() !== '' 
            ? image.tags.split(',').map(tag => tag.trim()).filter(tag => tag)
            : [];
        
        const imageTags = tagsArray.length > 0
            ? tagsArray.map(tag => `<span class="badge bg-secondary me-1 mb-1">${escapeHtml(tag)}</span>`).join('')
            : '<span class="text-muted">无标签</span>';
        
        container.innerHTML = `
            <div class="text-center mb-4">
                <img src="${escapeHtml(image.url)}" class="img-fluid rounded shadow" 
                     style="max-height: 300px;" alt="${escapeHtml(image.tags || '图片')}">
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">标签:</label>
                <div class="tags-container">${imageTags}</div>
            </div>
            
            <div class="mt-3">
                <label class="form-label fw-bold">图片链接:</label>
                <input type="text" class="form-control mb-2" 
                       value="${escapeHtml(image.url)}" readonly id="imageUrlInput"
                       onclick="this.select()">
                <button class="btn btn-outline-primary" data-url="${escapeHtml(image.url)}" 
                        onclick="copyUrlToClipboard(event)">
                    复制链接
                </button>
            </div>
            
            <div class="mt-4 text-center">
                <button class="btn btn-outline-primary" id="continueUploadBtn">继续上传</button>
            </div>
        `;
        
        uploadResult.style.display = 'block';
        if (uploadCard) {
            uploadCard.style.display = 'none';
        }
        
        const continueBtn = document.getElementById('continueUploadBtn');
        if (continueBtn) {
            continueBtn.addEventListener('click', resetUploadForm);
        }
        
        setTimeout(() => {
            uploadResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
    
    function resetUploadForm() {
        if (uploadCard) {
            uploadCard.style.display = 'block';
        }
        uploadResult.style.display = 'none';
        uploadForm.reset();
        resetFileSelection();
        
        setTimeout(() => {
            if (uploadCard) {
                uploadCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
    
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.textContent = message;
        
        const btnClose = document.createElement('button');
        btnClose.type = 'button';
        btnClose.className = 'btn-close';
        btnClose.setAttribute('data-bs-dismiss', 'alert');
        alertDiv.appendChild(btnClose);
        
        const container = document.querySelector('.container');
        if (container && container.firstChild) {
            container.insertBefore(alertDiv, container.firstChild);
        }
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    function copyUrlToClipboard(e) {
        const url = e.target.getAttribute('data-url');
        if (!url) return;
        
        const button = e.target.classList.contains('copy-url') 
            ? e.target 
            : e.target.closest('.copy-url') || e.target;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url)
                .then(() => showCopySuccess(button))
                .catch(() => fallbackCopyTextToClipboard(url, button));
        } else {
            fallbackCopyTextToClipboard(url, button);
        }
    }
    
    function showCopySuccess(button) {
        const originalText = button.innerHTML;
        const originalClass = button.className;
        
        button.innerHTML = '已复制';
        button.className = button.className.replace('btn-outline-primary', 'btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.className = originalClass;
        }, 2000);
    }
    
    function fallbackCopyTextToClipboard(url, button) {
        const textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopySuccess(button);
        } catch (err) {
            showAlert('复制失败，请手动复制链接：' + url, 'warning');
        }
        
        document.body.removeChild(textArea);
    }
    
    function copyToClipboard(text) {
        return new Promise((resolve, reject) => {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(resolve).catch(reject);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                textArea.style.left = '-9999px';
                document.body.appendChild(textArea);
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    resolve();
                } catch (err) {
                    document.body.removeChild(textArea);
                    reject(err);
                }
            }
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    fileInput.addEventListener('change', function() {
        handleFileSelection(fileInput.files[0]);
    });
    
    function initDragAndDrop() {
        if (!dropArea) return;
        
        dropArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('dragover');
        }
        
        function unhighlight() {
            dropArea.classList.remove('dragover');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                const file = files[0];
                if (isValidImageFile(file)) {
                    handleFileSelection(file);
                    fileInput.files = files;
                } else {
                    showAlert('请选择有效的图片文件（JPG、PNG、GIF、WebP）', 'danger');
                }
            }
        }
    }
    
    function handleFileSelection(file) {
        if (!file) return;
        
        if (!isValidImageFile(file)) {
            showAlert('请选择有效的图片文件（JPG、PNG、GIF、WebP）', 'danger');
            return;
        }
        
        const fileName = file.name;
        const fileSize = formatFileSize(file.size);
        
        dropArea.innerHTML = `
            <i class="fas fa-check-circle text-success display-1 mb-3"></i>
            <p class="mb-1 fw-bold">${escapeHtml(fileName)}</p>
            <small class="text-muted">${fileSize} · 点击重新选择或拖拽其他图片</small>
        `;
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('img');
                preview.src = e.target.result;
                preview.style.maxWidth = '100%';
                preview.style.maxHeight = '150px';
                preview.style.borderRadius = '8px';
                preview.style.marginTop = '1rem';
                preview.style.objectFit = 'cover';
                dropArea.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
    }
    
    function isValidImageFile(file) {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024;
        
        return validTypes.includes(file.type) && file.size <= maxSize;
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            resetFileSelection();
        }
    });
    
    function resetFileSelection() {
        fileInput.value = '';
        dropArea.innerHTML = `
            <i class="fas fa-file-image display-1 text-muted mb-3"></i>
            <p class="mb-2">拖放图片到此处或点击选择文件</p>
            <small class="text-muted">支持 JPG, PNG, GIF, WebP 格式</small>
        `;
        
        const previews = dropArea.querySelectorAll('img:not(.display-1)');
        previews.forEach(img => img.remove());
    }
});
