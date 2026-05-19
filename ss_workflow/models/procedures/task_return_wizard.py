# -*- coding: utf-8 -*-

from odoo import models, fields, exceptions


class ssw_task_return_wizard(models.TransientModel):
    _name = 'ssw.proc.task.return.wizard'
    _description = 'Task Return Wizard'

    task_id = fields.Many2one('ssw.proc.tasks', string='Task', required=True)
    reason  = fields.Text(string='Return Reason', required=True)

    def action_confirm(self):
        self.ensure_one()
        if not self.reason or not self.reason.strip():
            raise exceptions.ValidationError("Please provide a reason for returning this task.")
        self.task_id._do_close_task(reason=self.reason.strip())
        return {'type': 'ir.actions.act_window_close'}
