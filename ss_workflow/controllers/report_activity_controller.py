# -*- coding: utf-8 -*-

import io

import xlsxwriter
from odoo import http
from odoo.http import request
from odoo.tools.misc import get_lang


class WorkflowActivityReportController(http.Controller):

    @http.route('/ss_workflow_reports/activity/pdf', type='http', auth='user')
    def download_activity_pdf(self, date_from=None, date_to=None, department_id=None, **kw):
        department_id = int(department_id) if department_id else False
        report = request.env['ss.report.workflow_activity'].get_report(
            date_from=date_from,
            date_to=date_to,
            department_id=department_id,
        )

        html = request.env['ir.ui.view']._render_template(
            'ss_workflow.workflow_activity_pdf_manual',
            {
                'report_name': report['report_name'],
                'parameters': report['parameters'],
                'data': report['data'],
                'lang': get_lang(request.env).code,
            }
        )

        pdf = request.env['ir.actions.report']._run_wkhtmltopdf(
            [html],
            specific_paperformat_args={
                'orientation': 'Landscape',
                'margin_top': 15,
                'margin_bottom': 15,
                'margin_left': 10,
                'margin_right': 10,
            }
        )

        return request.make_response(
            pdf,
            headers=[
                ('Content-Type', 'application/pdf'),
                ('Content-Disposition', 'attachment; filename=workflow_activity.pdf'),
            ],
        )

    @http.route('/ss_workflow_reports/activity/xlsx', type='http', auth='user')
    def download_activity_xlsx(self, date_from=None, date_to=None, department_id=None, **kw):
        department_id = int(department_id) if department_id else False
        report = request.env['ss.report.workflow_activity'].get_report(
            date_from=date_from,
            date_to=date_to,
            department_id=department_id,
        )

        output = io.BytesIO()
        workbook = xlsxwriter.Workbook(output, {'in_memory': True})
        sheet = workbook.add_worksheet('Workflow Activity')

        header = workbook.add_format({'bold': True, 'border': 1, 'align': 'center'})
        cell = workbook.add_format({'border': 1})
        amt = workbook.add_format({'border': 1, 'align': 'right'})

        sheet.set_column(0, 0, 26)
        sheet.set_column(1, 6, 18)

        row = 0
        sheet.merge_range(row, 0, row, 6, report['report_name'], header)
        row += 1
        subtitle = f"{report['parameters']['date_from']} → {report['parameters']['date_to']}"
        if report['parameters'].get('department_name'):
            subtitle += f" | {report['parameters']['department_name']}"
        sheet.merge_range(row, 0, row, 6, subtitle, cell)
        row += 2

        sheet.write_row(
            row, 0,
            [
                'User',
                'Tickets Opened',
                'Tickets Closed',
                'Response Time (avg/hrs)',
                'Tasks Completed',
                'Task Duration (avg/hrs)',
                'Procedures Started',
            ],
            header
        )
        row += 1

        for item in report['data']:
            sheet.write(row, 0, item['user_name'], cell)
            sheet.write_number(row, 1, item['tickets_opened_num'], amt)
            sheet.write_number(row, 2, item['tickets_closed_num'], amt)
            sheet.write_number(row, 3, item['response_time_num'], amt)
            sheet.write_number(row, 4, item['tasks_completed_num'], amt)
            sheet.write_number(row, 5, item['task_duration_num'], amt)
            sheet.write_number(row, 6, item['procedures_started_num'], amt)
            row += 1

        workbook.close()
        output.seek(0)

        return request.make_response(
            output.read(),
            headers=[
                ('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                ('Content-Disposition', 'attachment; filename=workflow_activity.xlsx'),
            ],
        )
