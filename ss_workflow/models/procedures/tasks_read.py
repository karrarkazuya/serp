# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions
from datetime import date, datetime, timedelta


class ssw_task_read(models.Model):
    _name = 'ssw.proc.tasks.read'
    _description = 'Task'

    name                     = fields.Char(string='Title', required=True)
    description              = fields.Text()
    
    state                    = fields.Selection([("rejected", 'Rejected'), ("draft", 'Draft'), ("pending", 'Pending'), ("skipped", 'Skipped'), ("completed", 'Completed')], default='draft')
    
    task_sequance            = fields.Integer(string='Task Sequance')
    
    procedure_id             = fields.Many2one('ssw.procedures', string='Procedure')
    task_id                  = fields.Many2one('ssw.proc.tasks', string='Task')
    task_id_num              = fields.Integer(string='Task ID', compute='_set_task_id')
    can_see_task             = fields.Boolean(string='Can see task', default=False, compute='_set_can_see_task')
    
    assigned_to_user         = fields.Many2one('res.partner', string='Assigned User')
    assigned_to_dep          = fields.Many2one('ssw.departments', string='Assigned Department')
    
    resolve_deadline         = fields.Datetime(string='Resolve deadline')
    resolve_duration         = fields.Integer(string='Resolve duration', help="How many hours it took to resolve", default=0)
    resolve_deadline_passed  = fields.Integer(string='SLA Passed', help="How many hours it passed the SLA", default=0, tracking=True)
    available_at             = fields.Datetime(string='Available at')
    
    @api.depends('task_id')
    def _set_task_id(self):
        for item in self:
            item.task_id_num = item.task_id.id
            
    def _set_can_see_task(self):
        for item in self:
            try:
                value = item.task_id.state not in ['skipped', 'draft'] and self.env.user.partner_id.id in item.task_id.users_can_view.ids
            except:
                value = False
            item.can_see_task = value
            
    def view_task(self):
        for record in self:
            return {
                'name': 'Task Form',
                'type': 'ir.actions.act_window',
                'res_model': 'ssw.proc.tasks',  # Target model
                'view_mode': 'form',
                'res_id': record.task_id.id,  # ID of the record to open
                'target': 'current',  # Open in current window (or use 'new' for popup)
            }
    
    