/**
 * lab-app.js — Alpine.js Lab Application Data
 * Main application state and methods for Clinical Lab Results Viewer
 * Reads initial values from data-* attributes on the body element
 */
document.addEventListener('alpine:init', function() {

  Alpine.data('labApp', function(idInicial) {

    // ─── Read PHP-injected values from dataset ───────────
    var ds = document.body.dataset;

    return {
      // ── Core State ────────────────────────────────────
      idAbierto: idInicial || null,
      urlReporte: null,
      vistaReporte: false,
      nombreReporte: '',
      menuAbierto: false,
      dark: localStorage.getItem('lab-dark-mode') === 'true',
      filtrosAbiertos: false,
      densidad: localStorage.getItem('lab-densidad') || 'comoda',

      // ── Search & Filter ────────────────────────────────
      fechaBusqueda: ds.fecha || new Date().toISOString().split('T')[0],
      textoBusqueda: ds.busqueda || '',
      buscarTodos: ds.todos === '1',
      totalResultados: parseInt(ds.total) || 0,

      // ── Pagination ─────────────────────────────────────
      offsetActual: 100,
      hayMas: ds.hasMore === '1',
      cargandoMas: false,

      // ── Report viewer ──────────────────────────────────
      zoomReporte: 'w-full',
      cargandoReporte: false,

      // ── Entities Modal ─────────────────────────────────
      mostrarModalEntidades: false,
      mostrarResultsModal: false,
      formularioEntidades: {
        entidad: '',
        fechaInicio: new Date().toISOString().split('T')[0],
        fechaFin: new Date().toISOString().split('T')[0],
        soloConResultados: false,
        agruparPorFecha: true
      },
      resultadosEntidades: null,

      // ── WhatsApp ───────────────────────────────────────
      mostrarModalTelefono: false,
      telefonosDisponibles: [],
      urlWhatsappPendiente: '',

      // ── Patient Search Modal ───────────────────────────
      mostrarModalPacientesBusqueda: false,
      mostrarResultsPacientesBusqueda: false,
      busquedaAvanzada: false,
      formularioPacientesBusqueda: {
        identificacion: '',
        nombres: '',
        telefono: '',
        ciudad: '',
        entidad: '',
        soloConResultados: false,
        limit: 50
      },
      resultadosPacientesBusqueda: null,
      pacienteSeleccionado: null,
      examenesExpandidos: {},
      cargandoPacientesBusqueda: false,

      // ── Patient History Modal ─────────────────────────
      mostrarModalHistorial: false,
      historialPaciente: null,
      historialCargando: false,
      historialGraficoActivo: null,

      // ── Command Palette ────────────────────────────────
      showCommandPalette: false,
      commandQuery: '',
      commandResults: [],
      commandActiveIndex: 0,
      historialReciente: JSON.parse(localStorage.getItem('lab-historial-reciente') || '[]'),

      // ── Swipe state (mobile) ───────────────────────────
      swipeStartX: 0,
      swipeCurrentX: 0,
      swipeCardId: null,

      iniciarSwipe: function(e, cid) {
        if (window.innerWidth >= 768) return;
        this.swipeStartX = e.touches[0].clientX;
        this.swipeCurrentX = this.swipeStartX;
        this.swipeCardId = cid;
      },

      moverSwipe: function(e) {
        if (!this.swipeCardId) return;
        this.swipeCurrentX = e.touches[0].clientX;
        var diff = this.swipeStartX - this.swipeCurrentX;
        var el = document.getElementById('swipe-' + this.swipeCardId);
        if (el) {
          var tx = Math.max(-120, Math.min(0, -diff));
          el.style.transform = 'translateX(' + tx + 'px)';
        }
      },

      finalizarSwipe: function(e, cid) {
        if (!this.swipeCardId) return;
        var diff = this.swipeStartX - this.swipeCurrentX;
        var el = document.getElementById('swipe-' + this.swipeCardId);
        if (el) {
          if (diff > 60) {
            el.style.transform = 'translateX(-120px)';
          } else {
            el.style.transform = 'translateX(0)';
          }
        }
        this.swipeCardId = null;
      },

      resetSwipe: function(cid) {
        var el = document.getElementById('swipe-' + cid);
        if (el) el.style.transform = 'translateX(0)';
      },

      // ────────────────────────────────────────────────────
      // INIT
      // ────────────────────────────────────────────────────
      init: function() {
        var self = this;
        window.labAppInstance = this;

        // Watch density preference
        this.$watch('densidad', function(val) {
          localStorage.setItem('lab-densidad', val);
        });

        // Initialize dark mode
        if (this.dark) {
          document.documentElement.classList.add('dark');
        }

        // Init flatpickr (for modal date inputs only)
        if (typeof initFlatpickr !== 'undefined') {
          setTimeout(function() { initFlatpickr(); }, 100);
        }

        // Command palette custom event
        document.addEventListener('toggle-command-palette', function() {
          self.showCommandPalette = !self.showCommandPalette;
          if (self.showCommandPalette) {
            self.commandQuery = '';
            self.updateCommandResults();
          }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') {
            if (self.showCommandPalette) {
              self.showCommandPalette = false;
            } else if (self.mostrarModalTelefono) {
              self.mostrarModalTelefono = false;
            } else if (self.mostrarModalHistorial) {
              self.cerrarHistorial();
            } else if (self.mostrarModalEntidades) {
              self.mostrarModalEntidades = false;
            } else if (self.mostrarModalPacientesBusqueda) {
              self.cerrarModalPacientesBusqueda();
            } else if (self.vistaReporte) {
              self.urlReporte = null;
              self.vistaReporte = false;
            }
          }

          // Ctrl+K handled in utils.js
        });
      },

      // ────────────────────────────────────────────────────
      // NAVIGATION
      // ────────────────────────────────────────────────────
      toggleCard: function(cardId) {
        this.idAbierto = this.idAbierto === cardId ? null : cardId;
      },

      cambiarFecha: function() {
        var url = '?fecha=' + this.fechaBusqueda;
        if (this.buscarTodos) url += '&todos=1';
        if (this.textoBusqueda) url += '&buscar=' + encodeURIComponent(this.textoBusqueda);
        window.location.href = url;
      },

      buscarPacientes: function() {
        var url = '?fecha=' + this.fechaBusqueda;
        if (this.buscarTodos) url += '&todos=1';
        if (this.textoBusqueda) url += '&buscar=' + encodeURIComponent(this.textoBusqueda);
        window.location.href = url;
      },

      toggleDarkMode: function() {
        this.dark = !this.dark;
        localStorage.setItem('lab-dark-mode', this.dark.toString());
        document.documentElement.classList.toggle('dark', this.dark);

        // Re-init flatpickr with new theme
        if (typeof initFlatpickr !== 'undefined') {
          setTimeout(function() { initFlatpickr(); }, 100);
        }
      },

      // ────────────────────────────────────────────────────
      // PAGINATION (Infinite Scroll)
      // ────────────────────────────────────────────────────
      verificarScroll: function(e) {
        var self = this;
        var el = e.target;
        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 120 && this.hayMas && !this.cargandoMas) {
          this.cargarMas();
        }
      },

      cargarMas: async function() {
        if (this.cargandoMas || !this.hayMas) return;
        this.cargandoMas = true;

        try {
          var fd = new FormData();
          fd.append('action', 'cargar_mas');
          fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
          fd.append('fecha', this.fechaBusqueda);
          fd.append('buscar', this.textoBusqueda);
          fd.append('todos', this.buscarTodos ? '1' : '0');
          fd.append('offset', this.offsetActual);

          var r = await fetch('', { method: 'POST', body: fd });
          var d = await r.json();

          if (d.success) {
            var container = document.getElementById('lista-pacientes');
            var trigger = document.getElementById('load-more-trigger');
            if (trigger && container) {
              trigger.insertAdjacentHTML('beforebegin', d.html);
            }
            this.offsetActual = d.next_offset;
            this.hayMas = this.offsetActual < d.total;
            this.totalResultados = d.total;

            // Animate new cards
            if (typeof labToast !== 'undefined' && d.html) {
              // Optional: subtle toast for loaded items
            }
          }
        } catch (e) {
          console.error('Error loading more:', e);
          if (typeof labToast !== 'undefined') {
            labToast('Error al cargar mas resultados', 'error');
          }
        }

        this.cargandoMas = false;
      },

      // ────────────────────────────────────────────────────
      // REPORT VIEWER
      // ────────────────────────────────────────────────────
      cargarReporte: function(url, paciente) {
        this.urlReporte = url;
        this.vistaReporte = true;
        this.nombreReporte = 'Cargando...';
        this.cargandoReporte = true;
        this.zoomReporte = 'w-full';
        if (window.innerWidth < 768) {
          this.idAbierto = null;
        }
        if (paciente) this.agregarHistorialReciente(paciente);
      },

      cargarNombreReporte: function() {
        this.cargandoReporte = false;
        try {
          var frame = document.getElementById('frameReporte');
          if (frame && frame.contentDocument) {
            var title = frame.contentDocument.title ||
                        frame.contentDocument.querySelector('h1,h2')?.textContent ||
                        'Reporte';
            this.nombreReporte = title.substring(0, 60);
          }
        } catch (e) {
          this.nombreReporte = 'Reporte de examenes';
        }
      },

      ajustarZoom: function(modo) {
        this.zoomReporte = modo === 'fit' ? 'w-full' : 'h-screen';
      },

      descargarReporte: function() {
        if (!this.urlReporte) return;
        var a = document.createElement('a');
        a.href = this.urlReporte;
        a.target = '_blank';
        a.download = 'reporte.pdf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      },

      imprimirFrame: function() {
        var frame = document.getElementById('frameReporte');
        if (frame && frame.contentWindow) {
          frame.contentWindow.focus();
          frame.contentWindow.print();
        } else {
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'No se puede acceder al contenido para imprimir.',
              confirmButtonText: 'OK'
            });
          }
        }
      },

      // ────────────────────────────────────────────────────
      // WHATSAPP (Multi-phone)
      // ────────────────────────────────────────────────────
      enviarWhatsappMulti: function(fijo, movil, res2, urlCod) {
        var t = [];
        if (fijo && fijo.trim() && fijo.trim() !== '0') {
          t.push({ numero: fijo.trim(), tipo: 'Telefono fijo' });
        }
        if (movil && movil.trim() && movil.trim() !== '0') {
          t.push({ numero: movil.trim(), tipo: 'Telefono movil' });
        }
        if (res2 && res2.trim() && res2.trim() !== '0') {
          t.push({ numero: res2.trim(), tipo: 'Telefono alternativo' });
        }

        if (t.length === 0) {
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'warning',
              title: 'Sin telefono',
              text: 'Este paciente no tiene celular registrado.',
              confirmButtonColor: '#4f46e5',
              background: isDarkMode() ? '#131c31' : '#f8fafc',
              color: isDarkMode() ? '#f1f5f9' : '#1e293b'
            });
          }
          return;
        }

        if (t.length === 1) {
          this.enviarAWHatsapp(t[0].numero, urlCod);
          return;
        }

        this.telefonosDisponibles = t;
        this.urlWhatsappPendiente = urlCod || '';
        this.mostrarModalTelefono = true;
      },

      enviarAWHatsapp: function(numero) {
        var url = this.urlWhatsappPendiente
          ? decodeURIComponent(this.urlWhatsappPendiente)
          : (this.urlReporte ? decodeURIComponent(this.urlReporte) : '');
        var fullUrl = url ? (window.location.origin + '/' + url) : '';
        this.mostrarModalTelefono = false;

        var msg = 'Hola, envio tus resultados de laboratorio. Puedes verlos en: ' + fullUrl;

        navigator.clipboard.writeText(fullUrl).then(function() {
          if (typeof labToast !== 'undefined') {
            labToast('Enlace copiado. Redirigiendo a WhatsApp...', 'success');
          }
          setTimeout(function() {
            window.open('https://wa.me/' + numero + '?text=' + encodeURIComponent(msg), '_blank', 'noopener,noreferrer');
          }, 1500);
        }).catch(function() {
          window.open('https://wa.me/' + numero + '?text=' + encodeURIComponent(msg), '_blank', 'noopener,noreferrer');
        });
      },

      // ────────────────────────────────────────────────────
      // ENTITIES
      // ────────────────────────────────────────────────────
      abrirModalEntidades: function() {
        this.mostrarModalPacientesBusqueda = false;
        this.mostrarModalEntidades = true;
        this.mostrarResultsModal = false;
        this.formularioEntidades = {
          entidad: '',
          fechaInicio: new Date().toISOString().split('T')[0],
          fechaFin: new Date().toISOString().split('T')[0],
          soloConResultados: false,
          agruparPorFecha: true
        };
        this.resultadosEntidades = null;
      },

      actualizarEntidad: async function(sel) {
        var data = sel.dataset;
        var identificacion = data.identificacion;
        var fecha_examen = data.fechaExamen;
        var codexamen = data.codexamen;
        var nuevaEntidad = sel.value;

        if (!identificacion || !fecha_examen || !codexamen) {
          if (typeof Swal !== 'undefined') {
            Swal.fire('Error', 'Datos incompletos.', 'error');
          }
          return;
        }

        var result = await Swal.fire({
          title: 'Actualizar entidad?',
          text: 'Examen ' + codexamen,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#4f46e5',
          cancelButtonColor: '#94a3b8',
          confirmButtonText: 'Si, actualizar',
          cancelButtonText: 'Cancelar',
          background: isDarkMode() ? '#131c31' : '#f8fafc',
          color: isDarkMode() ? '#f1f5f9' : '#1e293b'
        });

        if (result.isConfirmed) {
          try {
            var fd = new URLSearchParams({
              action: 'actualizar_entidad',
              csrf_token: document.querySelector('meta[name="csrf-token"]').content,
              identificacion: identificacion,
              fecha_examen: fecha_examen,
              codexamen: codexamen,
              entidad: nuevaEntidad
            });

            var resp = await fetch('', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: fd.toString()
            });
            var d = await resp.json();

            if (d.success) {
              Swal.fire('Actualizado!', d.message, 'success');
            } else {
              Swal.fire('Error', d.message, 'error');
            }
          } catch (e) {
            Swal.fire('Error', 'Error al comunicarse con el servidor.', 'error');
          }
        }
      },

      consultarPorEntidades: async function() {
        if (!this.formularioEntidades.fechaInicio || !this.formularioEntidades.fechaFin) {
          Swal.fire({
            icon: 'warning',
            title: 'Fechas requeridas',
            text: 'Seleccione el rango de fechas.',
            confirmButtonColor: '#4f46e5',
            background: isDarkMode() ? '#131c31' : '#f8fafc',
            color: isDarkMode() ? '#f1f5f9' : '#1e293b'
          });
          return;
        }

        Swal.fire({
          title: 'Consultando...',
          text: 'Obteniendo resultados',
          allowOutsideClick: false,
          showConfirmButton: false,
          willOpen: function() { Swal.showLoading(); },
          background: isDarkMode() ? '#131c31' : '#f8fafc',
          color: isDarkMode() ? '#f1f5f9' : '#1e293b'
        });

        var fd = new FormData();
        fd.append('action', 'consulta_entidades');
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.append('entidad', this.formularioEntidades.entidad);
        fd.append('fecha_inicio', this.formularioEntidades.fechaInicio);
        fd.append('fecha_fin', this.formularioEntidades.fechaFin);
        fd.append('solo_resultados', this.formularioEntidades.soloConResultados ? '1' : '0');
        fd.append('agrupar_fecha', this.formularioEntidades.agruparPorFecha ? '1' : '0');

        try {
          var r = await fetch(window.location.href, { method: 'POST', body: fd });
          var d = await r.json();
          Swal.close();
          if (d.success) {
            this.resultadosEntidades = d;
            this.mostrarResultsModal = true;
          } else {
            Swal.fire('Error', d.message, 'error');
          }
        } catch (e) {
          Swal.close();
          Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
        }
      },

      prepararExportarExcel: function() {
        if (!this.resultadosEntidades || !this.resultadosEntidades.resultados) return;

        var self = this;
        var datos = [];
        if (this.formularioEntidades.agruparPorFecha) {
          this.resultadosEntidades.resultados.forEach(function(g) {
            g.examenes.forEach(function(e) { datos.push(e); });
          });
        } else {
          datos = this.resultadosEntidades.resultados;
        }

        var columnas = [
          { key: 'identificacion', label: 'Identificacion', checked: true },
          { key: 'paciente', label: 'Paciente', checked: true },
          { key: 'edad', label: 'Edad', checked: true },
          { key: 'genero', label: 'Genero', checked: false },
          { key: 'telefono', label: 'Telefono', checked: false },
          { key: 'fecha_examen', label: 'Fecha', checked: true },
          { key: 'entidad', label: 'Entidad', checked: true },
          { key: 'examen_nombre', label: 'Examen', checked: true },
          { key: 'examen_codigo', label: 'Codigo', checked: false },
          { key: 'examen_tipo', label: 'Tipo', checked: false },
          { key: 'resultado', label: 'Resultado', checked: true },
          { key: 'referencia', label: 'Referencia', checked: true },
          { key: 'estado', label: 'Estado', checked: true }
        ];

        var html = '<div class="text-left space-y-3">';
        html += '<p class="text-sm" style="color:' + (isDarkMode() ? '#94a3b8' : '#64748b') + '">Se exportaran <strong>' + datos.length + '</strong> registros.</p>';
        html += '<div class="grid grid-cols-2 gap-2 text-left">';
        columnas.forEach(function(c, i) {
          html += '<label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" class="excel-col-check" data-idx="' + i + '" ' + (c.checked ? 'checked' : '') + '><span>' + c.label + '</span></label>';
        });
        html += '</div></div>';

        Swal.fire({
          title: 'Exportar a Excel',
          html: html,
          showCancelButton: true,
          confirmButtonText: 'Descargar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#059669',
          background: isDarkMode() ? '#131c31' : '#f8fafc',
          color: isDarkMode() ? '#f1f5f9' : '#1e293b',
          preConfirm: function() {
            var checks = document.querySelectorAll('.excel-col-check');
            var seleccion = [];
            checks.forEach(function(chk) {
              var idx = parseInt(chk.getAttribute('data-idx'));
              if (chk.checked) seleccion.push(columnas[idx]);
            });
            return seleccion;
          }
        }).then(function(result) {
          if (result.isConfirmed && result.value.length > 0) {
            self.exportarExcel(result.value, datos);
          }
        });
      },

      exportarExcel: function(columnas, datos) {
        if (!columnas) {
          // Fallback: all columns
          columnas = [
            { key: 'identificacion', label: 'Identificacion' },
            { key: 'paciente', label: 'Paciente' },
            { key: 'edad', label: 'Edad' },
            { key: 'genero', label: 'Genero' },
            { key: 'telefono', label: 'Telefono' },
            { key: 'fecha_examen', label: 'Fecha' },
            { key: 'entidad', label: 'Entidad' },
            { key: 'examen_nombre', label: 'Examen' },
            { key: 'examen_codigo', label: 'Codigo' },
            { key: 'examen_tipo', label: 'Tipo' },
            { key: 'resultado', label: 'Resultado' },
            { key: 'referencia', label: 'Referencia' },
            { key: 'estado', label: 'Estado' }
          ];
        }
        if (!datos) {
          datos = [];
          if (this.formularioEntidades.agruparPorFecha) {
            this.resultadosEntidades.resultados.forEach(function(g) {
              g.examenes.forEach(function(e) { datos.push(e); });
            });
          } else {
            datos = this.resultadosEntidades.resultados;
          }
        }
        if (!datos.length) return;

        var headers = columnas.map(function(c) { return c.label; });
        var rows = datos.map(function(e) {
          return columnas.map(function(c) { return e[c.key] ?? ''; });
        });

        var ws = XLSX.utils.aoa_to_sheet([headers].concat(rows));
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Resultados');
        XLSX.writeFile(wb, 'resultados_entidades.xlsx');
      },

      imprimirResultsModal: function() {
        var self = this;
        var h = '<style>body{font-family:sans-serif;margin:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;font-size:11px}th{background:#f8fafc;font-weight:700}.hdr{background:#4f46e5;color:#fff;padding:10px;text-align:center}.grp{background:#eef2ff;font-weight:700}</style>';
        h += '<div class="hdr"><h1>Consulta por Entidad</h1><p>' + this.formularioEntidades.fechaInicio + ' - ' + this.formularioEntidades.fechaFin + '</p></div><br>';

        var renderTable = function(items) {
          h += '<table><thead><tr><th>ID</th><th>Paciente</th><th>Edad</th><th>Entidad</th><th>Examen</th><th>Resultado</th><th>Estado</th></tr></thead><tbody>';
          items.forEach(function(e) {
            h += '<tr><td>' + e.identificacion + '</td><td>' + e.paciente + '</td><td>' + Math.round(parseFloat(e.edad)) + '</td><td>' + e.entidad + '</td><td>' + e.examen_nombre + '</td><td>' + (e.resultado||'N/A') + '</td><td>' + (e.estado||'N/A') + '</td></tr>';
          });
          h += '</tbody></table>';
        };

        if (this.formularioEntidades.agruparPorFecha) {
          this.resultadosEntidades.resultados.forEach(function(g) {
            h += '<div class="grp">' + formatDate(g.fecha) + ' (' + g.cantidad + ')</div>';
            renderTable(g.examenes);
          });
        } else {
          renderTable(this.resultadosEntidades.resultados);
        }

        var w = window.open('', '_blank');
        w.document.write(h);
        w.document.close();
        w.print();
      },

      imprimirGrupoFechaModal: function(fecha) {
        var g = this.resultadosEntidades.resultados.find(function(x) { return x.fecha === fecha; });
        if (!g) return;

        var h = '<style>body{font-family:sans-serif;margin:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;font-size:11px}.hdr{background:#4f46e5;color:#fff;padding:10px;text-align:center}</style>';
        h += '<div class="hdr"><h1>' + formatDate(g.fecha) + '</h1></div>';
        h += '<table><thead><tr><th>ID</th><th>Paciente</th><th>Edad</th><th>Entidad</th><th>Examen</th><th>Resultado</th><th>Estado</th></tr></thead><tbody>';
        g.examenes.forEach(function(e) {
          h += '<tr><td>' + e.identificacion + '</td><td>' + e.paciente + '</td><td>' + Math.round(parseFloat(e.edad)) + '</td><td>' + e.entidad + '</td><td>' + e.examen_nombre + '</td><td>' + (e.resultado||'N/A') + '</td><td>' + (e.estado||'N/A') + '</td></tr>';
        });
        h += '</tbody></table>';

        var w = window.open('', '_blank');
        w.document.write(h);
        w.document.close();
        w.print();
      },

      verExamenIndividual: function(examen) {
        var params = new URLSearchParams({
          idx: examen.identificacion + '_' + examen.fecha_examen + '_' + examen.examen_codigo,
          identificacion: examen.identificacion,
          fecha: examen.fecha_examen,
          nombres: examen.paciente,
          tabla: examen.examen_tabla,
          info: examen.examen_nombre,
          tipo: examen.examen_tipo,
          codexamen: examen.examen_codigo,
          edad: examen.edad,
          embedido: 1,
          ver: 1
        });
        var url = 'printphp/print_examen.php?' + params.toString();
        this.cargarReporte(url, {
          id: examen.identificacion,
          nombre: examen.paciente,
          fecha: examen.fecha_examen,
          url: url,
          initials: (examen.paciente || 'P').substring(0, 2).toUpperCase()
        });
        this.mostrarModalEntidades = false;
      },

      // ────────────────────────────────────────────────────
      // PATIENT SEARCH MODAL
      // ────────────────────────────────────────────────────
      abrirModalPacientesBusqueda: function() {
        this.mostrarModalEntidades = false;
        this.formularioPacientesBusqueda = {
          identificacion: '',
          nombres: '',
          telefono: '',
          ciudad: '',
          entidad: '',
          soloConResultados: false,
          limit: 50
        };
        this.resultadosPacientesBusqueda = null;
        this.pacienteSeleccionado = null;
        this.examenesExpandidos = {};
        this.mostrarModalPacientesBusqueda = true;
        this.mostrarResultsPacientesBusqueda = false;
        this.busquedaAvanzada = false;
      },

      cerrarModalPacientesBusqueda: function() {
        this.mostrarModalPacientesBusqueda = false;
        this.resultadosPacientesBusqueda = null;
        this.pacienteSeleccionado = null;
        this.examenesExpandidos = {};
      },

      buscarPacientesModal: async function() {
        var f = this.formularioPacientesBusqueda;
        if (!f.identificacion.trim() && !f.nombres.trim() && !f.telefono.trim() && !f.ciudad.trim() && !f.entidad.trim()) {
          Swal.fire('Advertencia', 'Ingrese al menos un criterio.', 'warning');
          return;
        }

        this.cargandoPacientesBusqueda = true;
        this.resultadosPacientesBusqueda = null;
        this.pacienteSeleccionado = null;

        var fd = new FormData();
        fd.append('action', 'consulta_pacientes_resultados');
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.append('identificacion', f.identificacion);
        fd.append('nombres', f.nombres);
        fd.append('telefono', f.telefono);
        fd.append('ciudad', f.ciudad);
        fd.append('entidad', f.entidad);
        fd.append('include_examenes', '1');
        fd.append('solo_con_resultados', f.soloConResultados ? '1' : '0');
        fd.append('limit', f.limit);

        try {
          var r = await fetch(window.location.href, { method: 'POST', body: fd });
          var d = await r.json();
          if (d.success) {
            this.resultadosPacientesBusqueda = d;
            this.mostrarResultsPacientesBusqueda = true;
            var self = this;
            d.pacientes.forEach(function(_, i) {
              self.examenesExpandidos[i] = false;
            });
          } else {
            Swal.fire('Error', d.message, 'error');
          }
        } catch (e) {
          Swal.fire('Error', 'Error al conectar.', 'error');
        }

        this.cargandoPacientesBusqueda = false;
      },

      volverFormularioBusqueda: function() {
        this.mostrarResultsPacientesBusqueda = false;
      },

      seleccionarPaciente: function(p, i) {
        this.pacienteSeleccionado = (this.pacienteSeleccionado && this.pacienteSeleccionado.identificacion === p.identificacion) ? null : p;
        if (i !== undefined) {
          this.examenesExpandidos[i] = !this.examenesExpandidos[i];
        }
      },

      toggleExamenExpandido: function(i) {
        this.examenesExpandidos[i] = !this.examenesExpandidos[i];
      },

      verExamenPaciente: function(examen, paciente) {
        var params = new URLSearchParams({
          idx: Math.random().toString(36).substring(2, 15),
          identificacion: paciente.identificacion,
          fecha: examen.fecha,
          nombres: paciente.nombre_completo,
          tabla: examen.tabla,
          info: examen.nombre,
          tipo: examen.tipo,
          codexamen: examen.codigo,
          edad: paciente.edad,
          embedido: 1,
          ver: 1
        });
        var url = 'printphp/print_examen.php?' + params.toString();
        this.mostrarModalPacientesBusqueda = false;
        this.cargarReporte(url, {
          id: paciente.identificacion,
          nombre: paciente.nombre_completo,
          fecha: examen.fecha,
          url: url,
          initials: (paciente.nombre_completo || 'P').substring(0, 2).toUpperCase()
        });
      },

      exportarPacientesExcel: function() {
        if (!this.resultadosPacientesBusqueda || !this.resultadosPacientesBusqueda.pacientes || !this.resultadosPacientesBusqueda.pacientes.length) return;

        var self = this;
        var pacientes = this.resultadosPacientesBusqueda.pacientes;
        var columnas = [
          { key: 'identificacion', label: 'ID', checked: true },
          { key: 'nombre_completo', label: 'Nombre', checked: true },
          { key: 'edad', label: 'Edad', checked: true },
          { key: 'genero', label: 'Genero', checked: false },
          { key: 'telefono', label: 'Telefono', checked: true },
          { key: 'correo', label: 'Email', checked: false },
          { key: 'ciudad_residencia', label: 'Ciudad', checked: false },
          { key: 'entidad', label: 'Entidad', checked: false },
          { key: 'total_visitas', label: 'Visitas', checked: true },
          { key: 'ultima_visita', label: 'Ultima Visita', checked: true },
          { key: 'total_examenes', label: 'Examenes', checked: true }
        ];

        var html = '<div class="text-left space-y-3">';
        html += '<p class="text-sm" style="color:' + (isDarkMode() ? '#94a3b8' : '#64748b') + '">Se exportaran <strong>' + pacientes.length + '</strong> pacientes.</p>';
        html += '<div class="grid grid-cols-2 gap-2 text-left">';
        columnas.forEach(function(c, i) {
          html += '<label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" class="excel-col-check" data-idx="' + i + '" ' + (c.checked ? 'checked' : '') + '><span>' + c.label + '</span></label>';
        });
        html += '</div></div>';

        Swal.fire({
          title: 'Exportar pacientes a Excel',
          html: html,
          showCancelButton: true,
          confirmButtonText: 'Descargar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#059669',
          background: isDarkMode() ? '#131c31' : '#f8fafc',
          color: isDarkMode() ? '#f1f5f9' : '#1e293b',
          preConfirm: function() {
            var checks = document.querySelectorAll('.excel-col-check');
            var seleccion = [];
            checks.forEach(function(chk) {
              var idx = parseInt(chk.getAttribute('data-idx'));
              if (chk.checked) seleccion.push(columnas[idx]);
            });
            return seleccion;
          }
        }).then(function(result) {
          if (result.isConfirmed && result.value.length > 0) {
            var headers = result.value.map(function(c) { return c.label; });
            var rows = pacientes.map(function(p) {
              return result.value.map(function(c) { return p[c.key] ?? ''; });
            });
            var ws = XLSX.utils.aoa_to_sheet([headers].concat(rows));
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Pacientes');
            XLSX.writeFile(wb, 'pacientes_' + new Date().toISOString().split('T')[0] + '.xlsx');
          }
        });
      },

      // ────────────────────────────────────────────────────
      // RANGE BAR & SPARKLINE HELPERS
      // ────────────────────────────────────────────────────
      parseRangoJS: function(ref) {
        if (!ref || ref === 'N/A') return null;
        ref = ref.trim().replace(',', '.');
        var m;
        if ((m = ref.match(/([0-9]+\.?[0-9]*)\s*[-–]\s*([0-9]+\.?[0-9]*)/))) {
          return { min: parseFloat(m[1]), max: parseFloat(m[2]) };
        }
        if ((m = ref.match(/^<\s*=?\s*([0-9]+\.?[0-9]*)/))) return { min: null, max: parseFloat(m[1]) };
        if ((m = ref.match(/^>\s*=?\s*([0-9]+\.?[0-9]*)/))) return { min: parseFloat(m[1]), max: null };
        return null;
      },

      evaluarResultadoJS: function(valor, ref) {
        var numMatch = ('' + valor).replace(',', '.').match(/([0-9]+\.?[0-9]*)/);
        if (!numMatch) return 'normal';
        var v = parseFloat(numMatch[1]);
        var r = this.parseRangoJS(ref);
        if (!r) return 'normal';
        if (r.min !== null && r.max !== null) {
          if (v < r.min || v > r.max) {
            var rangeSize = r.max - r.min;
            var desviacion = (v < r.min) ? (r.min - v) : (v - r.max);
            return (rangeSize > 0 && desviacion / rangeSize > 0.5) ? 'danger' : 'warning';
          }
          return 'normal';
        }
        if (r.max !== null && v > r.max) return 'warning';
        if (r.min !== null && v < r.min) return 'warning';
        return 'normal';
      },

      renderRangeBar: function(containerId, valor, ref) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var numMatch = ('' + valor).replace(',', '.').match(/([0-9]+\.?[0-9]*)/);
        var r = this.parseRangoJS(ref);
        if (!numMatch || !r || (r.min === null && r.max === null)) {
          container.innerHTML = '';
          return;
        }
        var v = parseFloat(numMatch[1]);
        var min = r.min !== null ? r.min : 0;
        var max = r.max !== null ? r.max : (v > min ? v * 1.2 : min + 10);
        var range = max - min;
        var pct = Math.max(0, Math.min(100, ((v - min) / range) * 100));
        var normalLeft = r.min !== null ? ((r.min - min) / range) * 100 : 0;
        var normalWidth = r.max !== null ? ((r.max - (r.min !== null ? r.min : min)) / range) * 100 : 100;
        var estado = this.evaluarResultadoJS(valor, ref);
        var markerClass = estado === 'danger' ? 'danger' : (estado === 'warning' ? 'warning' : '');
        container.innerHTML =
          '<div class="range-bar">' +
            '<div class="range-bar-normal" style="left:' + normalLeft + '%;width:' + normalWidth + '%"></div>' +
            '<div class="range-bar-marker ' + markerClass + '" style="left:' + pct + '%"></div>' +
          '</div>' +
          '<div class="range-bar-labels"><span>' + min + '</span><span>Normal</span><span>' + max + '</span></div>';
      },

      renderSparkline: function(containerId, values) {
        var container = document.getElementById(containerId);
        if (!container || values.length < 2) return;
        var w = container.clientWidth || 200;
        var h = 32;
        var min = Math.min.apply(null, values);
        var max = Math.max.apply(null, values);
        var range = max - min || 1;
        var points = values.map(function(v, i) {
          var x = (i / (values.length - 1)) * w;
          var y = h - ((v - min) / range) * (h - 4) - 2;
          return x + ',' + y;
        });
        var area = '0,' + h + ' ' + points.join(' ') + ' ' + w + ',' + h;
        container.innerHTML =
          '<svg class="sparkline" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none">' +
            '<polygon class="sparkline-area" points="' + area + '"/>' +
            '<polyline class="sparkline" points="' + points.join(' ') + '"/>' +
          '</svg>';
      },
      highlightMatch: function(text, query) {
        if (!query) return text;
        var idx = text.toLowerCase().indexOf(query.toLowerCase());
        if (idx === -1) return text;
        return text.substring(0, idx) + '<span class="command-match">' + text.substring(idx, idx + query.length) + '</span>' + text.substring(idx + query.length);
      },

      fuzzyMatch: function(text, query) {
        if (!query) return true;
        text = text.toLowerCase();
        query = query.toLowerCase();
        var qi = 0;
        for (var i = 0; i < text.length; i++) {
          if (text[i] === query[qi]) qi++;
          if (qi === query.length) return true;
        }
        return false;
      },

      updateCommandResults: function() {
        var q = this.commandQuery.toLowerCase().trim();
        var self = this;

        // Static commands with categories and shortcuts
        var staticCommands = [
          { label: 'Entidades', description: 'Consulta por entidad y fecha', icon: 'bi-building', action: 'abrirModalEntidades', category: 'Acciones', shortcut: 'G E' },
          { label: 'Pacientes', description: 'Buscar pacientes', icon: 'bi-people-fill', action: 'abrirModalPacientesBusqueda', category: 'Acciones', shortcut: 'G P' },
          { label: 'Admin', description: 'Panel de administracion', icon: 'bi-shield-lock-fill', action: 'gotoAdmin', category: 'Navegacion', shortcut: 'G A' },
          { label: 'Dark Mode', description: 'Cambiar tema', icon: 'bi-moon-stars-fill', action: 'toggleDark', category: 'Preferencias', shortcut: 'G D' },
          { label: 'Imprimir todo', description: 'Imprimir todos los examenes del dia', icon: 'bi-printer-fill', action: 'printAll', category: 'Acciones', shortcut: 'G I' },
          { label: 'Densidad compacta', description: 'Lista de pacientes compacta', icon: 'bi-list', action: 'setDensityCompact', category: 'Preferencias', shortcut: '' },
          { label: 'Densidad comoda', description: 'Lista de pacientes comoda', icon: 'bi-list-nested', action: 'setDensityComfort', category: 'Preferencias', shortcut: '' }
        ];

        var results = [];

        if (!q && this.historialReciente.length > 0) {
          results.push({ type: 'category', label: 'Recientes' });
          this.historialReciente.slice(0, 5).forEach(function(p) {
            results.push({
              type: 'recent',
              label: p.nombre,
              description: p.id + ' · ' + p.fecha,
              icon: 'bi-clock-history',
              action: 'cargarReporte',
              url: p.url,
              initials: p.initials || 'P'
            });
          });
        }

        // Filter static commands
        var filteredStatic = staticCommands.filter(function(c) {
          return self.fuzzyMatch(c.label + ' ' + c.description, q);
        });

        if (filteredStatic.length > 0) {
          results.push({ type: 'category', label: 'Comandos' });
          filteredStatic.forEach(function(c) {
            results.push(Object.assign({ type: 'command' }, c));
          });
        }

        if (results.length === 0) {
          results.push({ type: 'empty', label: 'No se encontraron resultados', description: 'Prueba con otra palabra clave' });
        }

        this.commandResults = results;
        this.commandActiveIndex = 0;
      },

      ejecutarComando: function(cmd) {
        if (!cmd || cmd.type === 'category' || cmd.type === 'empty') return;
        this.showCommandPalette = false;
        switch (cmd.action) {
          case 'abrirModalEntidades':
            this.abrirModalEntidades();
            break;
          case 'abrirModalPacientesBusqueda':
            this.abrirModalPacientesBusqueda();
            break;
          case 'gotoAdmin':
            window.location.href = 'admin.php';
            break;
          case 'toggleDark':
            this.toggleDarkMode();
            break;
          case 'printAll':
            this.imprimirFrame();
            break;
          case 'setDensityCompact':
            this.densidad = 'compacta';
            break;
          case 'setDensityComfort':
            this.densidad = 'comoda';
            break;
          case 'cargarReporte':
            if (cmd.url) this.cargarReporte(cmd.url);
            break;
        }
      },

      agregarHistorialReciente: function(paciente) {
        if (!paciente || !paciente.id) return;
        var idx = this.historialReciente.findIndex(function(p) { return p.id === paciente.id && p.fecha === paciente.fecha; });
        if (idx !== -1) this.historialReciente.splice(idx, 1);
        this.historialReciente.unshift(paciente);
        if (this.historialReciente.length > 10) this.historialReciente.pop();
        localStorage.setItem('lab-historial-reciente', JSON.stringify(this.historialReciente));
      },

      // ────────────────────────────────────────────────────
      // PATIENT HISTORY MODAL
      // ────────────────────────────────────────────────────
      abrirHistorial: async function(identificacion, nombre, event) {
        if (event) event.stopPropagation();
        this.mostrarModalHistorial = true;
        this.historialPaciente = null;
        this.historialCargando = true;
        this.historialGraficoActivo = null;

        var fd = new FormData();
        fd.append('action', 'historial_paciente');
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        fd.append('identificacion', identificacion);

        try {
          var r = await fetch('', { method: 'POST', body: fd });
          var d = await r.json();
          if (d.success) {
            this.historialPaciente = d;
          } else {
            Swal.fire('Error', d.message, 'error');
            this.mostrarModalHistorial = false;
          }
        } catch (e) {
          Swal.fire('Error', 'Error al conectar con el servidor.', 'error');
          this.mostrarModalHistorial = false;
        }

        this.historialCargando = false;
      },

      cerrarHistorial: function() {
        this.mostrarModalHistorial = false;
        this.historialPaciente = null;
        this.historialGraficoActivo = null;
      },

      renderGraficoHistorial: function(examenNombre) {
        if (this.historialGraficoActivo === examenNombre) {
          this.historialGraficoActivo = null;
          return;
        }
        this.historialGraficoActivo = examenNombre;

        var puntos = [];
        if (!this.historialPaciente || !this.historialPaciente.fechas) return;
        var self = this;
        this.historialPaciente.fechas.forEach(function(grupo) {
          grupo.examenes.forEach(function(ex) {
            if (ex.nombre === examenNombre && ex.valor_numerico !== null) {
              puntos.push({ fecha: grupo.fecha, valor: ex.valor_numerico, status: ex.status, referencia: ex.referencia });
            }
          });
        });
        puntos.sort(function(a, b) { return a.fecha.localeCompare(b.fecha); });
        if (puntos.length < 1) return;

        var self = this;
        this.$nextTick(function() {
          var canvas = document.getElementById('historial-grafico-canvas');
          if (!canvas) return;
          self._dibujarGrafico(canvas, puntos, examenNombre);
        });
      },

      _dibujarGrafico: function(canvas, puntos, titulo) {
        var ctx = canvas.getContext('2d');
        var dpr = window.devicePixelRatio || 1;
        var rect = canvas.parentElement.getBoundingClientRect();
        var w = rect.width || 600;
        var h = 180;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        ctx.scale(dpr, dpr);

        var isDark = this.dark;
        var bgColor = isDark ? '#1e293b' : '#ffffff';
        var textColor = isDark ? '#94a3b8' : '#64748b';
        var lineColor = isDark ? '#4f46e5' : '#4f46e5';

        ctx.fillStyle = bgColor;
        ctx.fillRect(0, 0, w, h);

        var padLeft = 50, padRight = 20, padTop = 30, padBottom = 35;
        var chartW = w - padLeft - padRight;
        var chartH = h - padTop - padBottom;

        var valores = puntos.map(function(p) { return p.valor; });
        var valsMin = Math.min.apply(null, valores);
        var valsMax = Math.max.apply(null, valores);
        var valRange = valsMax - valsMin || 1;
        var yMin = valsMin - valRange * 0.1;
        var yMax = valsMax + valRange * 0.1;
        var yRange = yMax - yMin;

        var refMin = null, refMax = null;
        if (puntos[0].referencia && puntos[0].referencia !== 'N/A') {
          var refParsed = this.parseRangoJS(puntos[0].referencia);
          if (refParsed) { refMin = refParsed.min; refMax = refParsed.max; }
        }

        if (refMin !== null && refMax !== null) {
          var refTop = padTop + chartH * (1 - (refMax - yMin) / yRange);
          var refBot = padTop + chartH * (1 - (refMin - yMin) / yRange);
          ctx.fillStyle = isDark ? 'rgba(16,185,129,0.08)' : 'rgba(16,185,129,0.1)';
          ctx.fillRect(padLeft, refTop, chartW, refBot - refTop);
          ctx.strokeStyle = isDark ? 'rgba(16,185,129,0.3)' : 'rgba(16,185,129,0.4)';
          ctx.lineWidth = 1;
          ctx.setLineDash([4, 4]);
          ctx.beginPath(); ctx.moveTo(padLeft, refTop); ctx.lineTo(padLeft + chartW, refTop); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(padLeft, refBot); ctx.lineTo(padLeft + chartW, refBot); ctx.stroke();
          ctx.setLineDash([]);
        }

        ctx.fillStyle = textColor;
        ctx.font = '10px Inter, sans-serif';
        ctx.textAlign = 'right';
        for (var i = 0; i <= 4; i++) {
          var yy = yMin + (yRange * i / 4);
          var yPos = padTop + chartH * (1 - i / 4);
          ctx.fillText(yy.toFixed(1), padLeft - 8, yPos + 3);
          ctx.strokeStyle = isDark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.15)';
          ctx.lineWidth = 0.5;
          ctx.beginPath(); ctx.moveTo(padLeft, yPos); ctx.lineTo(padLeft + chartW, yPos); ctx.stroke();
        }

        var points = puntos.map(function(p, idx) {
          var x = puntos.length === 1 ? padLeft + chartW / 2 : padLeft + (idx / (puntos.length - 1)) * chartW;
          var y = padTop + chartH * (1 - (p.valor - yMin) / yRange);
          return { x: x, y: y, p: p };
        });

        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        for (var j = 1; j < points.length; j++) {
          ctx.lineTo(points[j].x, points[j].y);
        }
        ctx.strokeStyle = lineColor;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.stroke();

        points.forEach(function(pt) {
          var color = pt.p.status === 'danger' ? '#ef4444' : (pt.p.status === 'warning' ? '#f59e0b' : '#10b981');
          ctx.beginPath();
          ctx.arc(pt.x, pt.y, 5, 0, Math.PI * 2);
          ctx.fillStyle = color;
          ctx.fill();
          ctx.strokeStyle = bgColor;
          ctx.lineWidth = 2;
          ctx.stroke();
        });

        ctx.fillStyle = textColor;
        ctx.font = '10px Inter, sans-serif';
        ctx.textAlign = 'center';
        points.forEach(function(pt, idx) {
          var parts = pt.p.fecha.split('-');
          var label = parts[2] + '/' + parts[1];
          ctx.fillText(label, pt.x, h - 10);
        });

        ctx.fillStyle = isDark ? '#e2e8f0' : '#1e293b';
        ctx.font = 'bold 11px Inter, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(titulo, padLeft, 18);
      },

      imprimirHistorial: function() {
        var p = this.historialPaciente;
        if (!p) return;

        var h = '<style>body{font-family:sans-serif;margin:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;font-size:11px}th{background:#f8fafc;font-weight:700}.hdr{background:#4338ca;color:#fff;padding:10px;text-align:center}.grp{background:#eef2ff;font-weight:700;padding:6px 8px}</style>';
        h += '<div class="hdr"><h1>' + (p.paciente.nombre || '') + '</h1><p>ID: ' + (p.paciente.identificacion || '') + ' | Edad: ' + (p.paciente.edad || '') + ' | Visitas: ' + p.total_visitas + ' | Examenes: ' + p.total_examenes + '</p></div><br>';

        p.fechas.forEach(function(g) {
          var parts = g.fecha.split('-');
          var fechaFmt = parts[2] + '/' + parts[1] + '/' + parts[0];
          h += '<div class="grp">' + fechaFmt + ' (' + g.examenes.length + ' examenes)</div>';
          h += '<table><thead><tr><th>Examen</th><th>Resultado</th><th>Referencia</th><th>Estado</th></tr></thead><tbody>';
          g.examenes.forEach(function(ex) {
            h += '<tr><td>' + (ex.nombre || '') + '</td><td>' + (ex.resultado || 'N/A') + '</td><td>' + (ex.referencia || 'N/A') + '</td><td>' + (ex.status || '') + '</td></tr>';
          });
          h += '</tbody></table><br>';
        });

        var w = window.open('', '_blank');
        w.document.write(h);
        w.document.close();
        w.print();
      },
    };
  });

});
