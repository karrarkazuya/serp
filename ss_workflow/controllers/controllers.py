# -*- coding: utf-8 -*-

from odoo import http
from odoo.http import request


class TicketShareController(http.Controller):

    @http.route('/ticket/share/<string:token>', auth='none', type='http', csrf=False)
    def ticket_share(self, token, **kwargs):
        ticket = request.env['ssw.tickets'].sudo().search([
            ('share_token', '=', token),
            ('share_enabled', '=', True),
            ('deleted', '=', False),
        ], limit=1)

        if not ticket:
            return request.not_found()

        company = ticket.template_id.default_department.company
        company_name = company.name if company else ''
        return request.render('ss_workflow.ticket_share_page', {
            'ticket': ticket,
            'company_name': company_name,
        })
