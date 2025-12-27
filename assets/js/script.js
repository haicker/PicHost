document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = uploadProgress.querySelector('.progress-bar');
    const progressPercent = document.getElementById('progressPercent');
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('image');
    
    // 初始化拖放功能
    initDragAndDrop();
    
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const tags = document.getElementById('tags').value;
        
        if (!fileInput.files.length) {
            alert('请选择要上传的图片');
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
                    
                    // 自动复制链接到剪贴板
                    copyToClipboard(response.url).then(() => {
                        addImageToGallery({
                            url: response.url,
                            tags: tags,
                            upload_time: new Date().toISOString()
                        });
                    }).catch(() => {
                        // 如果自动复制失败，仍然显示图片
                        addImageToGallery({
                            url: response.url,
                            tags: tags,
                            upload_time: new Date().toISOString()
                        });
                        showAlert('图片上传成功！但链接复制失败，请手动复制', 'warning');
                    });
                } else {
                    showAlert('上传失败: ' + response.message, 'danger');
                }
            } catch (error) {
                showAlert('上传失败: 服务器响应错误', 'danger');
            }
            
            uploadProgress.style.display = 'none';
        });
        
        xhr.addEventListener('error', function() {
            showAlert('上传失败: 网络错误', 'danger');
            uploadProgress.style.display = 'none';
        });
        
        xhr.open('POST', 'upload.php');
        xhr.send(formData);
    }
    
    function addImageToGallery(image) {
        // 获取上传结果显示区域
        const uploadResult = document.getElementById('uploadResult');
        const container = document.getElementById('uploadedImageContainer');
        
        // 清空之前的结果
        container.innerHTML = '';
        
        // 创建图片显示区域
        const imageDisplay = document.createElement('div');
        imageDisplay.className = 'text-center mb-4';
        
        const img = document.createElement('img');
        img.className = 'img-fluid rounded shadow';
        img.style.maxHeight = '300px';
        img.src = image.url;
        img.alt = image.tags || '图片';
        
        imageDisplay.appendChild(img);
        
        // 创建标签显示区域
        const tagsSection = document.createElement('div');
        tagsSection.className = 'mb-3';
        
        const tagsLabel = document.createElement('label');
        tagsLabel.className = 'form-label fw-bold';
        tagsLabel.textContent = '标签:';
        
        const tagsDisplay = document.createElement('div');
        tagsDisplay.className = 'tags-container';
        
        if (image.tags && image.tags.trim() !== '') {
            const tagsArray = image.tags.split(',').map(tag => tag.trim());
            tagsArray.forEach(tag => {
                if (tag) {
                    const tagSpan = document.createElement('span');
                    tagSpan.className = 'badge bg-secondary me-1 mb-1';
                    tagSpan.textContent = tag;
                    tagsDisplay.appendChild(tagSpan);
                }
            });
        } else {
            const noTags = document.createElement('span');
            noTags.className = 'text-muted';
            noTags.textContent = '无标签';
            tagsDisplay.appendChild(noTags);
        }

        // 创建链接显示区域
        const linkSection = document.createElement('div');
        linkSection.className = 'mt-3';
        
        const linkLabel = document.createElement('label');
        linkLabel.className = 'form-label fw-bold';
        linkLabel.textContent = '图片链接:';
        
        const linkInput = document.createElement('input');
        linkInput.type = 'text';
        linkInput.className = 'form-control mb-2';
        linkInput.value = image.url;
        linkInput.readOnly = true;
        linkInput.id = 'imageUrlInput';
        
        const copyBtn = document.createElement('button');
        copyBtn.className = 'btn btn-primary';
        copyBtn.textContent = '复制链接';
        copyBtn.setAttribute('data-url', image.url);
        copyBtn.addEventListener('click', copyUrlToClipboard);
        
        linkSection.appendChild(linkLabel);
        linkSection.appendChild(document.createElement('br'));
        linkSection.appendChild(linkInput);
        linkSection.appendChild(document.createElement('br'));
        linkSection.appendChild(copyBtn);
        
        // 组合所有元素
        container.appendChild(imageDisplay);
        
        // 添加标签显示
        tagsSection.appendChild(tagsLabel);
        tagsSection.appendChild(document.createElement('br'));
        tagsSection.appendChild(tagsDisplay);
        container.appendChild(tagsSection);
        
        container.appendChild(linkSection);
        
        // 添加继续上传按钮
        const continueUploadSection = document.createElement('div');
        continueUploadSection.className = 'mt-4 text-center';
        
        const continueBtn = document.createElement('button');
        continueBtn.className = 'btn btn-outline-primary';
        continueBtn.textContent = '继续上传';
        continueBtn.id = 'continueUploadBtn';
        
        continueUploadSection.appendChild(continueBtn);
        container.appendChild(continueUploadSection);
        
        // 添加继续上传按钮事件监听器
        continueBtn.addEventListener('click', function() {
            // 显示上传组件，隐藏结果组件
            if (uploadCard) {
                uploadCard.style.display = 'block';
            }
            uploadResult.style.display = 'none';
            
            // 清空表单
            uploadForm.reset();
            
            // 滚动到上传组件
            setTimeout(() => {
                if (uploadCard) {
                    uploadCard.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }
            }, 100);
        });
        
        // 显示上传结果区域，隐藏上传组件
        uploadResult.style.display = 'block';
        const uploadCard = document.getElementById('uploadCard');
        if (uploadCard) {
            uploadCard.style.display = 'none';
        }
        
        // 添加点击输入框自动全选功能
        linkInput.addEventListener('click', function() {
            this.select();
        });
        
        // 自动滚动到上传结果区域
        setTimeout(() => {
            uploadResult.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }, 100);
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('zh-CN');
    }
    
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container');
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    function copyUrlToClipboard(e) {
        const url = e.target.getAttribute('data-url');
        
        // 优先使用现代的Clipboard API
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                showCopySuccess(e.target);
            }).catch(function() {
                // 如果现代API失败，尝试使用传统方法
                fallbackCopyTextToClipboard(url, e.target);
            });
        } else {
            // 浏览器不支持Clipboard API，使用传统方法
            fallbackCopyTextToClipboard(url, e.target);
        }
    }
    
    function showCopySuccess(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '已复制';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    }
    
    function fallbackCopyTextToClipboard(url, button) {
        const textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            showCopySuccess(button);
        } catch (err) {
            alert('复制失败，请手动复制链接: ' + url);
        }
        
        document.body.removeChild(textArea);
    }
    
    // 通用的复制到剪贴板函数
    function copyToClipboard(text) {
        return new Promise((resolve, reject) => {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(resolve).catch(reject);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
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
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('copy-url')) {
            copyUrlToClipboard(e);
        }
    });
    
    fileInput.addEventListener('change', function() {
        handleFileSelection(fileInput.files[0]);
    });
    
    // 拖放功能实现
    function initDragAndDrop() {
        if (!dropArea) return;
        
        // 点击上传区域触发文件选择
        dropArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // 拖放事件处理
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
        
        // 处理文件拖放
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
            <p class="mb-1 fw-bold">${fileName}</p>
            <small class="text-muted">${fileSize} • 点击重新选择或拖拽其他图片</small>
        `;
        
        // 添加预览功能（可选）
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('img');
                preview.src = e.target.result;
                preview.style.maxWidth = '100%';
                preview.style.maxHeight = '150px';
                preview.style.borderRadius = '8px';
                preview.style.marginTop = '1rem';
                dropArea.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
    }
    
    function isValidImageFile(file) {
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        return validTypes.includes(file.type) && file.size <= maxSize;
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // 添加键盘快捷键支持
    document.addEventListener('keydown', function(e) {
        // Ctrl + V 粘贴图片（如果支持）
        if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
            e.preventDefault();
            // 这里可以添加粘贴板图片处理逻辑
        }
        
        // Escape 键清除选择
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
    }
});