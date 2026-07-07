async function descargarPDF(id_analisis) {
    try {
        const res = await fetch(BASE_URL + 'controllers/AnalisisController.php?action=datos_pdf&id_analisis=' + id_analisis);
        const json = await res.json();
        if (json.success) {
            generarPDF(json.analisis);
            showToast('PDF descargado', 'success');
        } else {
            showToast(json.error || 'Error', 'danger');
        }
    } catch(e) {
        showToast('Error al descargar PDF', 'danger');
    }
}

// Generación PDF idéntica a la de nuevo análisis
async function generarPDF(a) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
    const W  = doc.internal.pageSize.getWidth();
    const pageH = doc.internal.pageSize.getHeight();
    let y = 0;

    doc.setFillColor(26, 86, 219);
    doc.rect(0, 0, W, 30, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(20);
    doc.text('RetinAI', 14, 13);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.text('Sistema de análisis retinal asistido por inteligencia artificial', 14, 20);
    doc.setFontSize(8);
    doc.text('Reporte #' + a.id, W - 14, 13, { align: 'right' });
    doc.text('Análisis retinal — Reporte médico', W - 14, 20, { align: 'right' });

    y = 38;
    doc.setDrawColor(230, 235, 245);
    doc.setFillColor(248, 250, 255);
    doc.roundedRect(10, y, W - 20, 28, 3, 3, 'FD');

    doc.setTextColor(26, 86, 219);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.text('MÉDICO TRATANTE', 16, y + 7);
    doc.setTextColor(30, 30, 40);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.text('Dr. ' + (a.nombre_medico || 'N/D'), 16, y + 15);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(90, 95, 110);
    const espLabel = a.especialidad_medico ? a.especialidad_medico : 'Médico Oftalmólogo';
    doc.text(espLabel + (a.cmp_medico ? '   ·   CMP: ' + a.cmp_medico : ''), 16, y + 22);

    let fechaStr = 'N/D';
    if(a.fecha_analisis) {
        const d = new Date(a.fecha_analisis.replace(' ', 'T'));
        fechaStr = d.toLocaleDateString('es-PE', { day:'2-digit', month:'long', year:'numeric' }) + '  —  ' + d.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
    }

    doc.setTextColor(26, 86, 219);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.text('FECHA Y HORA DEL ANÁLISIS', W - 14, y + 7, { align: 'right' });
    doc.setTextColor(30, 30, 40);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(fechaStr, W - 14, y + 15, { align: 'right' });
    if (a.codigo_paciente || a.dni_paciente) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(90, 95, 110);
        doc.text((a.codigo_paciente ? 'Cód. Paciente: ' + a.codigo_paciente : '') + (a.dni_paciente ? '   DNI: ' + a.dni_paciente : ''), W - 14, y + 22, { align: 'right' });
    }

    y += 36;
    const imgW  = 60;
    const imgH  = 55;
    const imgX  = 14;
    const imgY  = y;

    if (a.imagen_b64) {
        try {
            const ext = a.imagen_b64.startsWith('data:image/png') ? 'PNG' : 'JPEG';
            doc.addImage(a.imagen_b64, ext, imgX, imgY, imgW, imgH, '', 'MEDIUM');
            doc.setDrawColor(200, 210, 230);
            doc.setLineWidth(0.4);
            doc.rect(imgX, imgY, imgW, imgH);
        } catch(e) {}
    }

    doc.setFont('helvetica', 'italic');
    doc.setFontSize(7);
    doc.setTextColor(120, 125, 140);
    doc.text('Retinografía analizada', imgX + imgW / 2, imgY + imgH + 5, { align: 'center' });

    const rX = imgX + imgW + 8;
    const rW = W - rX - 10;
    const badgeColor = a.alerta_anomalia ? [220, 38, 38] : [5, 150, 105];

    doc.setFillColor(...badgeColor);
    doc.roundedRect(rX, imgY, rW, 22, 3, 3, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.text('RESULTADO PRINCIPAL', rX + rW / 2, imgY + 7, { align: 'center' });
    doc.setFontSize(14);
    doc.text((a.resultado_principal.charAt(0).toUpperCase() + a.resultado_principal.slice(1).toLowerCase()), rX + rW / 2, imgY + 17, { align: 'center' });

    doc.setFillColor(240, 245, 255);
    doc.setDrawColor(210, 220, 240);
    doc.roundedRect(rX, imgY + 25, rW, 12, 2, 2, 'FD');
    doc.setTextColor(26, 86, 219);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text('Confianza: ' + Number(a.probabilidad_principal).toFixed(1) + '%', rX + rW / 2, imgY + 33, { align: 'center' });

    const barY0 = imgY + 40;
    const clases = [
        { label: 'Retinopatía Diabética', val: a.probabilidad_diabetes, color: [220, 38, 38]  },
        { label: 'Glaucoma',              val: a.probabilidad_glaucoma, color: [217, 119, 6]  },
        { label: 'Catarata',              val: a.probabilidad_catarata, color: [37, 99, 235]  },
        { label: 'Normal',                val: a.probabilidad_normal,   color: [5, 150, 105]  },
    ];
    const barMaxW = rW - 4;
    clases.forEach((c, i) => {
        const by = barY0 + i * 11;
        const pct = Math.min(100, Math.max(0, Number(c.val)));
        const filledW = (pct / 100) * barMaxW;
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(50, 55, 70);
        doc.text(c.label, rX, by + 3.5);
        doc.setTextColor(80, 85, 100);
        doc.text(pct.toFixed(1) + '%', rX + rW, by + 3.5, { align: 'right' });
        doc.setFillColor(230, 234, 242);
        doc.roundedRect(rX, by + 5, barMaxW, 3.5, 1, 1, 'F');
        if (filledW > 0) {
            doc.setFillColor(...c.color);
            doc.roundedRect(rX, by + 5, filledW, 3.5, 1, 1, 'F');
        }
    });

    y = Math.max(imgY + imgH + 8, barY0 + clases.length * 11 + 6);

    // Diagnóstico Médico
    if (a.diagnostico_medico) {
        doc.setFillColor(248, 250, 255);
        doc.setDrawColor(210, 220, 240);
        const diagLines = doc.splitTextToSize(a.diagnostico_medico, W - 32);
        const diagH = diagLines.length * 4.5 + 14;
        doc.roundedRect(10, y, W - 20, diagH, 2, 2, 'FD');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(26, 86, 219);
        doc.text('DIAGNÓSTICO Y OBSERVACIONES CLÍNICAS', 16, y + 7);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(30, 30, 40);
        doc.text(diagLines, 16, y + 14);
        y += diagH + 8;
    }

    y += 4;
    doc.setFillColor(255, 251, 235);
    doc.setDrawColor(253, 230, 138);
    doc.roundedRect(10, y, W - 20, 14, 2, 2, 'FD');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.5);
    doc.setTextColor(146, 64, 14);
    doc.text('⚠  RESULTADO REFERENCIAL', 16, y + 6);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(120, 80, 20);
    doc.text('Este análisis fue generado por un modelo de inteligencia artificial. No constituye diagnóstico definitivo.', 16, y + 11);

    y += 20;
    const leyenda = 'Resultado generado por modelo de inteligencia artificial con fines de apoyo diagnóstico. La decisión clínica final corresponde al médico tratante.';
    doc.setFillColor(241, 245, 249);
    doc.setDrawColor(203, 213, 225);
    const leyendaLines = doc.splitTextToSize(leyenda, W - 32);
    const leyendaH = leyendaLines.length * 4.5 + 8;
    doc.roundedRect(10, y, W - 20, leyendaH, 2, 2, 'FD');
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(8);
    doc.setTextColor(71, 85, 105);
    doc.text(leyendaLines, 16, y + 7);

    doc.setFillColor(26, 86, 219);
    doc.rect(0, pageH - 12, W, 12, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.text('RetinAI — Sistema de análisis retinal asistido por IA', 14, pageH - 5);
    doc.text('Generado: ' + new Date().toLocaleString('es-PE'), W - 14, pageH - 5, { align: 'right' });

    const fechaFile = new Date().toISOString().slice(0, 10);
    doc.save('RetinAI_Reporte_' + a.id + '_' + fechaFile + '.pdf');
}

function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast ' + (type || '');
    t.style.display = 'flex';
    setTimeout(() => t.style.display = 'none', 3000);
}
