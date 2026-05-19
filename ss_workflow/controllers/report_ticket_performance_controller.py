# -*- coding: utf-8 -*-

import io

import xlsxwriter
from odoo import http
from odoo.http import request
from odoo.tools.misc import get_lang


class WorkflowTicketPerformanceController(http.Controller):

    @http.route('/ss_workflow_reports/ticket_performance/pdf', type='http', auth='user')
    def download_ticket_performance_pdf(self, date_from=None, date_to=None, **kw):
        report = request.env['ss.report.workflow_ticket_performance'].get_report(
            date_from=date_from,
            date_to=date_to,
        )

        html = request.env['ir.ui.view']._render_template(
            'ss_workflow.workflow_ticket_performance_pdf_manual',
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
                ('Content-Disposition', 'attachment; filename=ticket_performance.pdf'),
            ],
        )

    @http.route('/ss_workflow_reports/ticket_performance/xlsx', type='http', auth='user')
    def download_ticket_performance_xlsx(self, date_from=None, date_to=None, **kw):
        report = request.env['ss.report.workflow_ticket_performance'].get_report(
            date_from=date_from,
            date_to=date_to,
        )

        output = io.BytesIO()
        workbook = xlsxwriter.Workbook(output, {'in_memory': True})
        sheet = workbook.add_worksheet('Ticket Performance')

        header = workbook.add_format({'bold': True, 'border': 1, 'align': 'center'})
        cell = workbook.add_format({'border': 1})
        amt = workbook.add_format({'border': 1, 'align': 'right'})

        sheet.set_column(0, 0, 28)
        sheet.set_column(1, 8, 18)

        row = 0
        sheet.merge_range(row, 0, row, 8, report['report_name'], header)
        row += 1
        sheet.merge_range(
            row, 0, row, 8,
            f"{report['parameters']['date_from']} → {report['parameters']['date_to']}",
            cell
        )
        row += 2

        sheet.write_row(
            row, 0,
            [
                'Ticket Template',
                'Opened',
                'Finished',
                'Avg Finish (hrs)',
                'SLA Passed (sum/hrs)',
                '1st Longest Department',
                '1st Time (hrs)',
                '2nd Longest Department',
                '2nd Time (hrs)',
            ],
            header
        )
        row += 1

        for item in report['data']:
            sheet.write(row, 0, item['template_name'], cell)
            sheet.write_number(row, 1, item['opened_count_num'], amt)
            sheet.write_number(row, 2, item['finished_count_num'], amt)
            sheet.write_number(row, 3, item['avg_finish_num'], amt)
            sheet.write_number(row, 4, item['sla_passed_num'], amt)
            sheet.write(row, 5, item['longest_1_name'], cell)
            sheet.write_number(row, 6, item['longest_1_time_num'], amt)
            sheet.write(row, 7, item['longest_2_name'], cell)
            sheet.write_number(row, 8, item['longest_2_time_num'], amt)
            row += 1

        workbook.close()
        output.seek(0)

        return request.make_response(
            output.read(),
            headers=[
                ('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                ('Content-Disposition', 'attachment; filename=ticket_performance.xlsx'),
            ],
        )
