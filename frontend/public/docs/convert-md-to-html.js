const fs = require('fs');
const path = require('path');

// Template HTML base
const htmlTemplate = `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{TITLE}} - Mi Biblioteca Personal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/fontawesome/css/fontawesome.min.css" rel="stylesheet">
    <link href="/vendor/fontawesome/css/solid.min.css" rel="stylesheet">
    <link href="/vendor/fontawesome/css/brands.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css" rel="stylesheet">
    <style>
        .help-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .help-section {
            margin: 2rem 0;
        }
        .feature-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .code-block {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
            overflow-x: auto;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .info-box {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .success-box {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .breadcrumb {
            background-color: transparent;
            margin-bottom: 1rem;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1rem;
            overflow-x: auto;
        }
        .toc-sticky {
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="help-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="{{ICON}} me-3"></i>{{TITLE}}</h1>
                    <p class="lead mb-0">{{SUBTITLE}}</p>
                </div>
                <div class="col-md-4 text-end">
                    <button onclick="window.history.back()" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </button>
                    <button onclick="window.close()" class="btn btn-light">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        {{BREADCRUMB}}

        <div class="row">
            {{SIDEBAR}}
            
            <div class="col-md-9">
                {{CONTENT}}
                
                <div class="text-muted mt-5">
                    <small><i class="fas fa-calendar-alt me-2"></i>Última actualización: 18 de agosto de 2025</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>`;

// Función para convertir markdown a HTML básico
function convertMarkdownToHtml(markdown) {
    let html = markdown;
    
    // Headers
    html = html.replace(/^### (.*$)/gim, '<h4>$1</h4>');
    html = html.replace(/^## (.*$)/gim, '<h3>$1</h3>');
    html = html.replace(/^# (.*$)/gim, '<h2>$1</h2>');
    
    // Code blocks
    html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Bold and italic
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Lists
    html = html.replace(/^[\s]*\* (.*$)/gim, '<li>$1</li>');
    html = html.replace(/^[\s]*\- (.*$)/gim, '<li>$1</li>');
    html = html.replace(/^[\s]*\d+\. (.*$)/gim, '<li>$1</li>');
    
    // Wrap consecutive list items in ul/ol
    html = html.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
    
    // Paragraphs
    html = html.replace(/\n\n/g, '</p><p>');
    html = '<p>' + html + '</p>';
    
    // Clean up
    html = html.replace(/<p><\/p>/g, '');
    html = html.replace(/<p>(<h[1-6]>)/g, '$1');
    html = html.replace(/(<\/h[1-6]>)<\/p>/g, '$1');
    html = html.replace(/<p>(<ul>)/g, '$1');
    html = html.replace(/(<\/ul>)<\/p>/g, '$1');
    html = html.replace(/<p>(<pre>)/g, '$1');
    html = html.replace(/(<\/pre>)<\/p>/g, '$1');
    
    return html;
}

// Configuración de archivos a convertir
const filesToConvert = [
    {
        input: 'docs/api/auth.md',
        output: 'docs/api/auth.html',
        title: 'Authentication API',
        subtitle: 'Endpoints de autenticación y gestión de usuarios',
        icon: 'fas fa-shield-alt'
    },
    {
        input: 'docs/api/movies.md',
        output: 'docs/api/movies.html',
        title: 'Movies API',
        subtitle: 'Endpoints para gestión de películas',
        icon: 'fas fa-film'
    },
    {
        input: 'docs/api/library.md',
        output: 'docs/api/library.html',
        title: 'Library API',
        subtitle: 'Endpoints generales de biblioteca',
        icon: 'fas fa-library'
    },
    {
        input: 'docs/architecture/backend.md',
        output: 'docs/architecture/backend.html',
        title: 'Arquitectura Backend',
        subtitle: 'Estructura y patrones de diseño del backend',
        icon: 'fas fa-server'
    },
    {
        input: 'docs/LOGGING_SYSTEM.md',
        output: 'docs/LOGGING_SYSTEM.html',
        title: 'Sistema de Logging',
        subtitle: 'Documentación del sistema de logging estructurado',
        icon: 'fas fa-file-contract'
    }
];

console.log('🔄 Iniciando conversión de archivos Markdown a HTML...\n');

filesToConvert.forEach(file => {
    try {
        // Leer archivo markdown
        if (!fs.existsSync(file.input)) {
            console.log(`⚠️  Archivo no encontrado: ${file.input}`);
            return;
        }
        
        const markdownContent = fs.readFileSync(file.input, 'utf8');
        
        // Convertir a HTML
        const htmlContent = convertMarkdownToHtml(markdownContent);
        
        // Generar breadcrumb basado en la ruta
        const pathParts = file.input.split('/');
        let breadcrumbHtml = `
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../../frontend/public/help.html">Documentación</a></li>
                <li class="breadcrumb-item"><a href="../README.html">Docs</a></li>`;
        
        if (pathParts[1] !== 'README.md') {
            const folder = pathParts[1];
            const folderName = folder.charAt(0).toUpperCase() + folder.slice(1);
            breadcrumbHtml += `<li class="breadcrumb-item"><a href="../${folder}/README.html">${folderName}</a></li>`;
        }
        
        breadcrumbHtml += `<li class="breadcrumb-item active" aria-current="page">${file.title}</li>
            </ol>
        </nav>`;
        
        // Generar sidebar básico
        const sidebarHtml = `
        <div class="col-md-3">
            <div class="card toc-sticky">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Navegación</h5>
                </div>
                <div class="card-body">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="../README.html">📚 Documentación Principal</a>
                        <a class="nav-link" href="../frontend/README.html">🖥️ Frontend</a>
                        <a class="nav-link" href="../api/README.html">🔌 API</a>
                        <a class="nav-link" href="../architecture/backend.html">🏗️ Arquitectura</a>
                    </nav>
                </div>
            </div>
        </div>`;
        
        // Reemplazar placeholders en el template
        let finalHtml = htmlTemplate
            .replace(/{{TITLE}}/g, file.title)
            .replace(/{{SUBTITLE}}/g, file.subtitle)
            .replace(/{{ICON}}/g, file.icon)
            .replace(/{{BREADCRUMB}}/g, breadcrumbHtml)
            .replace(/{{SIDEBAR}}/g, sidebarHtml)
            .replace(/{{CONTENT}}/g, htmlContent);
        
        // Crear directorio si no existe
        const outputDir = path.dirname(file.output);
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }
        
        // Escribir archivo HTML
        fs.writeFileSync(file.output, finalHtml);
        
        console.log(`✅ Convertido: ${file.input} → ${file.output}`);
        
    } catch (error) {
        console.log(`❌ Error convirtiendo ${file.input}:`, error.message);
    }
});

console.log('\n🎉 Conversión completada!\n');
console.log('📋 Archivos HTML generados:');
filesToConvert.forEach(file => {
    if (fs.existsSync(file.output)) {
        console.log(`   • ${file.output}`);
    }
});

console.log('\n💡 Los archivos HTML están listos para ser visualizados desde help.html');
