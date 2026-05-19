# -*- coding: utf-8 -*-

from odoo import models, fields, api


class ss_workflow_inputs_subs(models.Model):
    _name = 'ssw.tickets.inputs.subs'
    _description = 'Ticket Input Subs'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name          = fields.Char(string='Title')
    input_id      = fields.Many2one('ssw.tickets.inputs', string='Ticket Input', required=True, tracking=True)
    deleted       = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True

